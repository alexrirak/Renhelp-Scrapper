<?php

namespace App\Spiders;

use App\Spiders\Processors\SaveTutorialToDatabaseProcessor;
use App\Spiders\Processors\SaveTutorialToDatabaseProcessorextends;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        print("Parsing Categories \n");
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
        print("Parsing Tutorials \n");
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

        $data = [
            'title' => $this->extractTitle($response),
            'content' => Str::trim($response->filter('[data-role="commentContent"]')->first()->html()),
            'author' => $this->extractAuthor($response),
            'level_number' => $this->extractLevelNumber($level),
            'level_label' => $this->extractLevelLabel($level),
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
