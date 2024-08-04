<?php

namespace App\Manager;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Class EmailManager
 *
 * The manager for handling emails
 *
 * @package App\Manager
 */
class EmailManager
{
    private LogManager $logManager;
    private MailerInterface $mailer;
    private ErrorManager $errorManager;
    private DatabaseManager $databaseManager;

    public function __construct(
        LogManager $logManager,
        MailerInterface $mailer,
        ErrorManager $errorManager,
        DatabaseManager $databaseManager
    ) {
        $this->mailer = $mailer;
        $this->logManager = $logManager;
        $this->errorManager = $errorManager;
        $this->databaseManager = $databaseManager;
    }

    /**
     * Send a default email with a subject and message
     *
     * @param string $recipient The recipient email
     * @param string $subject The email subject
     * @param string $message The email message
     *
     * @return void
     */
    public function sendDefaultEmail(string $recipient, string $subject, string $message): void
    {
        $this->sendEmail($recipient, $subject, [
            'subject' => $subject,
            'message' => $message,
            'time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Send a monitoring status email
     *
     * @param string $recipient The recipient email
     * @param string $serviceName The service name
     * @param string $message The message
     * @param string $currentStatus The status
     *
     * @return void
     */
    public function sendMonitoringStatusEmail(string $recipient, string $serviceName, string $message, string $currentStatus): void
    {
        $this->sendEmail($recipient, 'monitoring status', [
            'serviceName' => $serviceName,
            'monitoringMesssage' => $message,
            'monitoringStatus' => $currentStatus,
            'time' => date('Y-m-d H:i:s')
        ], 'monitoring-status');
    }

    /**
     * Send an email with a template and context data
     *
     * @param string $recipient The recipient email
     * @param string $subject The email subject
     * @param array<mixed> $context The email context
     * @param string $template The email template
     *
     * @throws TransportExceptionInterface If the email sending fails
     *
     * @return void
     */
    public function sendEmail(string $recipient, string $subject, array $context, string $template = 'default'): void
    {
        // check if mailer is enabled
        if ($_ENV['MAILER_ENABLED'] == 'false') {
            return;
        }

        // build email message
        $email = (new TemplatedEmail())
            ->from($_ENV['MAILER_USERNAME'])
            ->to($recipient)
            ->subject($subject)
            ->htmlTemplate('email/' . $template . '.twig')
            ->context($context);

        try {
            // send email
            $this->mailer->send($email);

            // log email sending
            if (!$this->databaseManager->isDatabaseDown()) {
                $this->logManager->log(
                    name: 'email-send',
                    message: 'email sent to ' . $recipient . ' with subject: ' . $subject,
                    level: LogManager::LEVEL_INFO
                );
            }
        } catch (TransportExceptionInterface $e) {
            $this->errorManager->handleError(
                message: 'email sending failed: ' . $e->getMessage(),
                code: Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
