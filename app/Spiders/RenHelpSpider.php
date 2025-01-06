<?php

namespace App\Spiders;

use App\Spiders\Processors\SaveTutorialToDatabaseProcessor;
use App\Spiders\Processors\SaveTutorialToDatabaseProcessorextends;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use RoachPHP\Downloader\Middleware\RequestDeduplicationMiddleware;
use RoachPHP\Downloader\Middleware\UserAgentMiddleware;
use RoachPHP\Extensions\LoggerExtension;
use RoachPHP\Extensions\StatsCollectorExtension;
use RoachPHP\Http\Request;
use RoachPHP\Http\Response;
use RoachPHP\Spider\BasicSpider;
use RoachPHP\Spider\ParseResult;

class RenHelpSpider extends BasicSpider
{
    public array $startUrls = [
        //
    ];

    public array $downloaderMiddleware = [
        RequestDeduplicationMiddleware::class,
        [UserAgentMiddleware::class, ['userAgent' => 'Mozilla/5.0 (compatible; RoachPHP/0.1.0)']],
    ];

    public array $spiderMiddleware = [
        //
    ];

    public array $itemProcessors = [
        SaveTutorialToDatabaseProcessor::class,
    ];

    public array $extensions = [
        LoggerExtension::class,
        StatsCollectorExtension::class,
    ];

    public int $concurrency = 2;

    public int $requestDelay = 1;

    public function parseCategories(Response $response): \Generator
    {
        Log::info("Parsing Categories");
        $pages = $response
            ->filter('h4.ipsDataItem_title > a')
            ->links();
        Log::info("Categories Found:");
        foreach ($pages as $page) {
            Log::info($page->getUri());

            yield $this->request('GET', $page->getUri(), 'parseTutorials');
        }
    }

    public function parseTutorials(Response $response): \Generator
    {
        Log::info("Parsing Tutorials");
        $pages = $response
            ->filter('h4.ipsDataItem_title > span.ipsType_break > a')
            ->links();
        Log::info("Tutorials Found:");
        foreach ($pages as $page) {
            Log::info($page->getUri());
            yield $this->request('GET', $page->getUri(),'parse');
        }
    }

    /**
     * @return Generator<ParseResult>
     */
    public function parse(Response $response): Generator
    {
        $srcUrl = $response->getUri();
        Log::info("Parsing Tutorial Page: " . $srcUrl);

        $level = $this->extractLevel($response);

        $content_html = Str::trim($response->filter('[data-role="commentContent"]')->first()->html());
        //replace src attributes of img tags with data-src attribute
        $content_html = preg_replace_callback('/<img([^>]*?)\s+src="([^"]+)"([^>]*?)\s+data-src="([^"]+)"/', function($matches) {
            // Replace src with the value of data-src
            return '<img' . $matches[1] . ' src="' . $matches[4] . '"' . $matches[3];
        }, $content_html);


        $converter = new HtmlConverter();
        $converter->getEnvironment()->addConverter(new TableConverter());
        $markdown = $converter->convert($content_html);
        // remove span tags
        $markdown = preg_replace('/<\/?span[^>]*>/', '', $markdown);

        // find videos and replace
        // looks for <iframe data-embed-src="..." ...></iframe>
        // extract data-embed-src and replace with [video]URL[/video]
        $markdown = preg_replace_callback('/<iframe allowfullscreen="true" data-embed-src="([^"]+)" frameborder="0" height="[^"]+" src="[^"]+" width="[^"]+"><\/iframe>/', function ($matches) {
            return "[video]{$matches[1]}[/video]";
        }, $markdown);

        // remove div tags
        $markdown = preg_replace('/<\/?div[^>]*>/', '', $markdown);


        $data = [
            'title' => $this->extractTitle($response),
            'content_html' => $content_html,
            'content_markdown' => $markdown,
            'author' => $this->extractAuthor($response),
            'level_number' => $this->extractLevelNumber($level),
            'level_label' => $this->extractLevelLabel($level),
            'tags' => $this->extractTags($response),
            'srcUrl' => $srcUrl,
        ];

        yield $this->item($data);
    }

    protected function extractTitle(Response $response): string
    {
        // check if title has an author and remove it
        $title = $response->filter('h1.ipsType_pageTitle > span.ipsType_break > span')->text();
        $author = Str::after($title, ' - Author: ');
        if ($author && $author !== $title) {
            return Str::before($title, ' - Author: ');
        } else {
            return $title;
        }
    }


    protected function extractAuthor(Response $response): string
    {
        // check if title has an author in it
        // format would be Author: Greg Hjelstrom
        $title = $response->filter('h1.ipsType_pageTitle > span.ipsType_break > span')->text();
        $author = Str::after($title, 'Author: ');
        if ($author && $author !== $title) {
            return Str::trim($author);
        }

        // First attempt: Select the <a> tag within the author element
        $authorNode = $response->filter('h3.cAuthorPane_author > strong > a');
        if ($authorNode->count() > 0) {
            return Str::trim($authorNode->first()->text());
        }

        // Second attempt: Fallback to <strong> if <a> is missing
        $authorNode = $response->filter('h3.cAuthorPane_author > strong');
        if ($authorNode->count() > 0) {
            return Str::trim($authorNode->first()->text());
        }

        // Default case: Author unknown
        return 'Unknown';
    }

    protected function extractLevel(Response $response): string
    {
        $levelNode = $response->filter('h1.ipsType_pageTitle > span > a > span');
        if ($levelNode->count() > 0) {
            return Str::trim($levelNode->text());
        }

        return 'Unknown';
    }

    protected function extractLevelNumber(String $level): int
    {
        $levelNumber = Str::before($level, ' - ');
        if($levelNumber && is_numeric($levelNumber)) {
            return (int)$levelNumber;
        }
        return 0;
    }

    protected function extractLevelLabel(String $level): string
    {
        $levelLabel = Str::after($level, ' - ');
        if($levelLabel) {
            return $levelLabel;
        }
        return 'Unknown';
    }

    protected function extractTags(Response $response): array
    {
        // Initialize an empty array to store tags
        $tags = [];

        // Select all <li> elements within the tags <ul>
        $response->filter('div.ipsPageHeader > div > div > ul.ipsTags.ipsList_inline > li')->each(function ($node) use (&$tags) {
            // Extract the tag label or text
            $tag = $node->filter('a.ipsTag > span')->text();
            $tags[] = trim($tag);
        });

        return $tags;
    }

    /** @return Request[] */
    protected function initialRequests(): array
    {
        return [
            new Request(
                'GET',
                'https://multiplayerforums.com/forum/57-renhelpnet-read-only-archives/',
                // Specify a different parse method for
                // the intial request.
                [$this, 'parseCategories']
            ),
        ];
    }
}
