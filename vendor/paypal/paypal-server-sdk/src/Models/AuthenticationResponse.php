<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Models;

use stdClass;

/**
 * Results of Authentication such as 3D Secure.
 */
class AuthenticationResponse implements \JsonSerializable
{
    /**
     * @var string|null
     */
    private $liabilityShift;

    /**
     * @var ThreeDSecureAuthenticationResponse|null
     */
    private $threeDSecure;

    /**
     * Returns Liability Shift.
     * Liability shift indicator. The outcome of the issuer's authentication.
     */
    public function getLiabilityShift(): ?string
    {
        return $this->liabilityShift;
    }

    /**
     * Sets Liability Shift.
     * Liability shift indicator. The outcome of the issuer's authentication.
     *
     * @maps liability_shift
     */
    public function setLiabilityShift(?string $liabilityShift): void
    {
        $this->liabilityShift = $liabilityShift;
    }

    /**
     * Returns Three D Secure.
     * Results of 3D Secure Authentication.
     */
    public function getThreeDSecure(): ?ThreeDSecureAuthenticationResponse
    {
        return $this->threeDSecure;
    }

    /**
     * Sets Three D Secure.
     * Results of 3D Secure Authentication.
     *
     * @maps three_d_secure
     */
    public function setThreeDSecure(?ThreeDSecureAuthenticationResponse $threeDSecure): void
    {
        $this->threeDSecure = $threeDSecure;
    }

    /**
     * Encode this object to JSON
     *
     * @param bool $asArrayWhenEmpty Whether to serialize this model as an array whenever no fields
     *        are set. (default: false)
     *
     * @return array|stdClass
     */
    #[\ReturnTypeWillChange] // @phan-suppress-current-line PhanUndeclaredClassAttribute for (php < 8.1)
    public function jsonSerialize(bool $asArrayWhenEmpty = false)
    {
        $json = [];
        if (isset($this->liabilityShift)) {
            $json['liability_shift'] = LiabilityShiftIndicator::checkValue($this->liabilityShift);
        }
        if (isset($this->threeDSecure)) {
            $json['three_d_secure']  = $this->threeDSecure;
        }

        return (!$asArrayWhenEmpty && empty($json)) ? new stdClass() : $json;
    }
}