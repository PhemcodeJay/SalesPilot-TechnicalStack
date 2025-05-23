<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Models\Builders;

use Core\Utils\CoreHelper;
use PaypalServerSdkLib\Models\Address;
use PaypalServerSdkLib\Models\AuthenticationResponse;
use PaypalServerSdkLib\Models\GooglePayCardResponse;

/**
 * Builder for model GooglePayCardResponse
 *
 * @see GooglePayCardResponse
 */
class GooglePayCardResponseBuilder
{
    /**
     * @var GooglePayCardResponse
     */
    private $instance;

    private function __construct(GooglePayCardResponse $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new google pay card response Builder object.
     */
    public static function init(): self
    {
        return new self(new GooglePayCardResponse());
    }

    /**
     * Sets name field.
     */
    public function name(?string $value): self
    {
        $this->instance->setName($value);
        return $this;
    }

    /**
     * Sets last digits field.
     */
    public function lastDigits(?string $value): self
    {
        $this->instance->setLastDigits($value);
        return $this;
    }

    /**
     * Sets type field.
     */
    public function type(?string $value): self
    {
        $this->instance->setType($value);
        return $this;
    }

    /**
     * Sets brand field.
     */
    public function brand(?string $value): self
    {
        $this->instance->setBrand($value);
        return $this;
    }

    /**
     * Sets billing address field.
     */
    public function billingAddress(?Address $value): self
    {
        $this->instance->setBillingAddress($value);
        return $this;
    }

    /**
     * Sets authentication result field.
     */
    public function authenticationResult(?AuthenticationResponse $value): self
    {
        $this->instance->setAuthenticationResult($value);
        return $this;
    }

    /**
     * Initializes a new google pay card response object.
     */
    public function build(): GooglePayCardResponse
    {
        return CoreHelper::clone($this->instance);
    }
}
