<?php

class EmailProviderFactory
{
    public static function create(string $provider): EmailProviderInterface
    {
        switch ($provider) {
            case 'mailgun':
                return new MailgunProvider(
                    $_ENV['MAILGUN_API_KEY'],
                    $_ENV['MAILGUN_SANDBOX'],
                    $_ENV['MAILGUN_EMAIL']
                );
            case 'mailtrap':
                return new MailtrapProvider(
                    $_ENV['MAILTRAP_API_KEY'],
                    $_ENV['MAILTRAP_INBOX_ID']
                );
            case 'brevo':
                return new BrevoProvider(
                    $_ENV['BREVO_API_KEY'],
                    null
                );
            default:
                throw new Exception("Unsupported provider: $provider");
        }
    }
}