<?php

namespace App\Event\Subscriber;

use App\Manager\LogManager;
use Psr\Log\LoggerInterface;
use App\Manager\DatabaseManager;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ExceptionEventSubscriber
 *
 * The subscriber for the exception event
 *
 * @package App\Event\Subscriber
 */
class ExceptionEventSubscriber implements EventSubscriberInterface
{
    private LogManager $logManager;
    private LoggerInterface $logger;
    private DatabaseManager $databaseManager;

    public function __construct(
        LogManager $logManager,
        LoggerInterface $logger,
        DatabaseManager $databaseManager
    ) {
        $this->logger = $logger;
        $this->logManager = $logManager;
        $this->databaseManager = $databaseManager;
    }

    /**
     * Get the subscribed events
     *
     * @return array<string> The subscribed events
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * Handle the exception event
     *
     * @param ExceptionEvent $event The exception event
     *
     * @return void
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        // get the error caller
        $errorCaler = $event->getThrowable()->getTrace()[0]['function'];

        // get the error message
        $message = $event->getThrowable()->getMessage();

        // check if the error caller is the logger
        if ($errorCaler != 'handleError') {
            return;
        }

        // check if the event can be logged in database
        if ($this->canBeEventLogged($message)) {
            // log the exception to admin-suite database
            if (!$this->databaseManager->isDatabaseDown()) {
                $this->logManager->log('exception', $message, LogManager::LEVEL_CRITICAL);
            }
        }

        // log the error message with monolog (file storage)
        $this->logger->error($message);
    }

    /**
     * Check if an event can be logged
     *
     * @param string $errorMessage The error message
     *
     * @return bool If the event can be logged
     */
    public function canBeEventLogged(string $errorMessage): bool
    {
        // list of error patterns that should block event dispatch
        $blockedErrorPatterns = [
            'log-error:',
            'Unknown database',
            'Connection refused',
            'database connection',
            'Base table or view not found',
            'An exception occurred in the driver'
        ];

        // check patterns in the error message
        foreach ($blockedErrorPatterns as $pattern) {
            // check if error message contains a blocked pattern
            if (strpos($errorMessage, $pattern) !== false) {
                return false;
            }
        }

        // if no blocked patterns are found, return true
        return true;
    }
}
