<?php

namespace App\Annotation;

use Attribute;

/**
 * Class Authorization
 *
 * Annotation for authorization middleware
 *
 * @package App\Annotation
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Authorization
{
    private string $authorization;

    public function __construct(string $authorization)
    {
        $this->authorization = $authorization;
    }

    public function getAuthorization(): string
    {
        return $this->authorization;
    }
}
