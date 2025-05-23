<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Models\Builders;

use Core\Utils\CoreHelper;
use PaypalServerSdkLib\Models\OrderAuthorizeResponse;
use PaypalServerSdkLib\Models\OrderAuthorizeResponsePaymentSource;
use PaypalServerSdkLib\Models\Payer;

/**
 * Builder for model OrderAuthorizeResponse
 *
 * @see OrderAuthorizeResponse
 */
class OrderAuthorizeResponseBuilder
{
    /**
     * @var OrderAuthorizeResponse
     */
    private $instance;

    private function __construct(OrderAuthorizeResponse $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Initializes a new order authorize response Builder object.
     */
    public static function init(): self
    {
        return new self(new OrderAuthorizeResponse());
    }

    /**
     * Sets create time field.
     */
    public function createTime(?string $value): self
    {
        $this->instance->setCreateTime($value);
        return $this;
    }

    /**
     * Sets update time field.
     */
    public function updateTime(?string $value): self
    {
        $this->instance->setUpdateTime($value);
        return $this;
    }

    /**
     * Sets id field.
     */
    public function id(?string $value): self
    {
        $this->instance->setId($value);
        return $this;
    }

    /**
     * Sets payment source field.
     */
    public function paymentSource(?OrderAuthorizeResponsePaymentSource $value): self
    {
        $this->instance->setPaymentSource($value);
        return $this;
    }

    /**
     * Sets intent field.
     */
    public function intent(?string $value): self
    {
        $this->instance->setIntent($value);
        return $this;
    }

    /**
     * Sets processing instruction field.
     */
    public function processingInstruction($value): self
    {
        $this->instance->setProcessingInstruction($value);
        return $this;
    }

    /**
     * Sets payer field.
     */
    public function payer(?Payer $value): self
    {
        $this->instance->setPayer($value);
        return $this;
    }

    /**
     * Sets purchase units field.
     */
    public function purchaseUnits(?array $value): self
    {
        $this->instance->setPurchaseUnits($value);
        return $this;
    }

    /**
     * Sets status field.
     */
    public function status(?string $value): self
    {
        $this->instance->setStatus($value);
        return $this;
    }

    /**
     * Sets links field.
     */
    public function links(?array $value): self
    {
        $this->instance->setLinks($value);
        return $this;
    }

    /**
     * Initializes a new order authorize response object.
     */
    public function build(): OrderAuthorizeResponse
    {
        return CoreHelper::clone($this->instance);
    }
}
