<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Models\Builders;

use Core\Utils\CoreHelper;
use PaypalServerSdkLib\Models\ExperienceContext;

/**
 * Builder for model ExperienceContext
 *
 * @see ExperienceContext
 */
class ExperienceContextBuilder
{
    /**
     * @var ExperienceContext
     */
    private $instance;

    private function __construct(ExperienceContext $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new experience context Builder object.
     */
    public static function init(): self
    {
        return new self(new ExperienceContext());
    }

    /**
     * Sets brand name field.
     */
    public function brandName(?string $value): self
    {
        $this->instance->setBrandName($value);
        return $this;
    }

    /**
     * Sets locale field.
     */
    public function locale(?string $value): self
    {
        $this->instance->setLocale($value);
        return $this;
    }

    /**
     * Sets shipping preference field.
     */
    public function shippingPreference(?string $value): self
    {
        $this->instance->setShippingPreference($value);
        return $this;
    }

    /**
     * Sets return url field.
     */
    public function returnUrl(?string $value): self
    {
        $this->instance->setReturnUrl($value);
        return $this;
    }

    /**
     * Sets cancel url field.
     */
    public function cancelUrl(?string $value): self
    {
        $this->instance->setCancelUrl($value);
        return $this;
    }

    /**
     * Initializes a new experience context object.
     */
    public function build(): ExperienceContext
    {
        return CoreHelper::clone($this->instance);
    }
}
