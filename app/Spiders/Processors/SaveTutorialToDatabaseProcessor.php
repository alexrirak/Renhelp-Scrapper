<?php

namespace App\Spiders\Processors;

use App\Models\Tutorial;
use RoachPHP\ItemPipeline\ItemInterface;
use RoachPHP\ItemPipeline\Processors\ItemProcessorInterface;
use RoachPHP\Support\Configurable;

class SaveTutorialToDatabaseProcessor implements ItemProcessorInterface
{
    use Configurable;
    public function processItem(ItemInterface $item): ItemInterface
    {
        Tutorial::create([
            'title' => $item->get('title'),
            'content_html' => $item->get('content_html'),
            'content_markdown' => $item->get('content_markdown'),
            'srcUrl' => $item->get('srcUrl'),
            'author' => $item->get('author'),
            'level_number' => $item->get('level_number'),
            'level_label' => $item->get('level_label'),
            'tags' => $item->get('tags'),
        ]);

        return $item;
    }
}
