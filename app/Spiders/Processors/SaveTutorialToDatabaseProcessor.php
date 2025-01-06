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
            'content' => $item->get('content'),
            'srcUrl' => $item->get('srcUrl'),
            'author' => $item->get('author'),
            'level_number' => $item->get('level_number'),
            'level_label' => $item->get('level_label'),
            'tags' => $item->get('tags'),
        ]);

        return $item;
    }
}
