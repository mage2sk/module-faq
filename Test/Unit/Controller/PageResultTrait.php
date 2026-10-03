<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;

trait PageResultTrait
{
    protected array $pageCalls = [];

    protected function pageFactory(): PageFactory
    {
        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($value) {
            $this->pageCalls['title'] = (string)$value;
        });
        $title->method('set')->willReturnCallback(function ($value) {
            $this->pageCalls['title_set'] = $value === null ? null : (string)$value;
        });
        $config = $this->createStub(Config::class);
        $config->method('getTitle')->willReturn($title);
        $config->method('setDescription')->willReturnCallback(function ($value) {
            $this->pageCalls['description'] = $value;
        });
        $config->method('setKeywords')->willReturnCallback(function ($value) {
            $this->pageCalls['keywords'] = $value;
        });
        $config->method('addRemotePageAsset')->willReturnCallback(function ($url, $type, $props = []) use ($config) {
            $this->pageCalls['remote'][] = [$url, $type, $props];
            return $config;
        });
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('setActiveMenu')->willReturnCallback(function ($menu) use ($page) {
            $this->pageCalls['menu'] = $menu;
            return $page;
        });
        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        $this->pageCalls['page'] = $page;

        return $factory;
    }
}
