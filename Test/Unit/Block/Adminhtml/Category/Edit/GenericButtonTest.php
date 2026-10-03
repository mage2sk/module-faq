<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Block\Adminhtml\Category\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class GenericButtonTest extends TestCase
{
    protected array $params = [];

    protected function widgetContext(): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => $this->params[$key] ?? $default
        );
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn ($route = '', $params = []) => '/admin/' . $route . ($params ? '?' . http_build_query($params) : '')
        );
        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getUrlBuilder')->willReturn($url);

        return $context;
    }

    protected function idParam(): string
    {
        return 'category_id';
    }

    protected function idGetter(): string
    {
        return 'getCategoryId';
    }

    protected function buttonClass(string $name): string
    {
        return 'Panth\\Faq\\Block\\Adminhtml\\Category\\Edit\\' . $name;
    }

    protected function deleteMessage(): string
    {
        return 'Are you sure you want to delete this FAQ category?';
    }

    public function testBackButtonPointsToGrid(): void
    {
        $class = $this->buttonClass('BackButton');
        $data = (new $class($this->widgetContext()))->getButtonData();

        $this->assertSame("location.href = '/admin/*/*/';", $data['on_click']);
        $this->assertSame('back', $data['class']);
        $this->assertSame(10, $data['sort_order']);
    }

    public function testDeleteButtonOnlyForExistingEntity(): void
    {
        $class = $this->buttonClass('DeleteButton');
        $this->assertSame([], (new $class($this->widgetContext()))->getButtonData());

        $this->params[$this->idParam()] = '7';
        $data = (new $class($this->widgetContext()))->getButtonData();

        $this->assertSame(
            "deleteConfirm('" . $this->deleteMessage() . "', '/admin/*/*/delete?" . $this->idParam() . "=7')",
            $data['on_click']
        );
        $this->assertSame('delete', $data['class']);
    }

    public function testSaveButtons(): void
    {
        $save = $this->buttonClass('SaveButton');
        $continue = $this->buttonClass('SaveAndContinueButton');

        $saveData = (new $save($this->widgetContext()))->getButtonData();
        $continueData = (new $continue($this->widgetContext()))->getButtonData();

        $this->assertSame('save primary', $saveData['class']);
        $this->assertSame(['button' => ['event' => 'save']], $saveData['data_attribute']['mage-init']);
        $this->assertSame('save', $saveData['data_attribute']['form-role']);
        $this->assertSame(
            ['button' => ['event' => 'saveAndContinueEdit']],
            $continueData['data_attribute']['mage-init']
        );
        $this->assertSame(80, $continueData['sort_order']);
    }

    public function testIdAndUrlHelpers(): void
    {
        $this->params[$this->idParam()] = '3';
        $class = $this->buttonClass('BackButton');
        $button = new $class($this->widgetContext());
        $getter = $this->idGetter();

        $this->assertSame('3', $button->$getter());
        $this->assertSame('/admin/a/b?x=1', $button->getUrl('a/b', ['x' => 1]));
    }
}
