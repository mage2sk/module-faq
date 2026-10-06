<?php
declare(strict_types=1);

namespace Panth\Faq\Test\Unit\Controller;

use Magento\Backend\App\Action\Context as BackendContext;
use Magento\Framework\App\Action\Context as FrontendContext;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Message\ManagerInterface as MessageManager;

trait ActionContextTrait
{
    protected array $messages = ['success' => [], 'error' => [], 'warning' => [], 'exception' => []];
    protected array $redirect = [];
    protected array $requestParams = [];
    protected $postValue = null;
    protected array $acl = [];

    protected function request(): HttpRequest
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getParam')->willReturnCallback(
            fn ($key, $default = null) => array_key_exists($key, $this->requestParams)
                ? $this->requestParams[$key]
                : $default
        );
        $request->method('getPostValue')->willReturnCallback(fn () => $this->postValue);

        return $request;
    }

    protected function redirectResult(): Redirect
    {
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(
            function ($path, $params = []) use ($redirect) {
                $this->redirect = ['path' => $path, 'params' => $params];
                return $redirect;
            }
        );

        return $redirect;
    }

    protected function messageManager(): MessageManager
    {
        $manager = $this->createStub(MessageManager::class);
        $manager->method('addSuccessMessage')->willReturnCallback(
            function ($message) use ($manager) {
                $this->messages['success'][] = (string)$message;
                return $manager;
            }
        );
        $manager->method('addErrorMessage')->willReturnCallback(
            function ($message) use ($manager) {
                $this->messages['error'][] = (string)$message;
                return $manager;
            }
        );
        $manager->method('addWarningMessage')->willReturnCallback(
            function ($message) use ($manager) {
                $this->messages['warning'][] = (string)$message;
                return $manager;
            }
        );
        $manager->method('addExceptionMessage')->willReturnCallback(
            function ($exception, $message = null) use ($manager) {
                $this->messages['exception'][] = (string)$message;
                return $manager;
            }
        );

        return $manager;
    }

    /**
     * @param class-string $class
     */
    protected function actionContext(
        string $class = BackendContext::class,
        ?HttpRequest $request = null,
        ?ResultFactory $resultFactory = null,
        ?EventManager $eventManager = null
    ) {
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirectResult());

        $context = $this->createStub($class);
        $context->method('getRequest')->willReturn($request ?? $this->request());
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getResultFactory')->willReturn($resultFactory ?? $this->createStub(ResultFactory::class));
        $context->method('getMessageManager')->willReturn($this->messageManager());
        $context->method('getEventManager')->willReturn($eventManager ?? $this->createStub(EventManager::class));

        if ($class === BackendContext::class || is_subclass_of($class, BackendContext::class)) {
            $authorization = $this->createStub(AuthorizationInterface::class);
            $authorization->method('isAllowed')->willReturnCallback(
                fn ($resource) => in_array($resource, $this->acl, true)
            );
            $context->method('getAuthorization')->willReturn($authorization);
        }

        return $context;
    }

    protected function frontendContext(?HttpRequest $request = null)
    {
        return $this->actionContext(FrontendContext::class, $request);
    }

    protected function isAllowed(object $controller): bool
    {
        $method = new \ReflectionMethod($controller, '_isAllowed');

        return (bool)$method->invoke($controller);
    }
}
