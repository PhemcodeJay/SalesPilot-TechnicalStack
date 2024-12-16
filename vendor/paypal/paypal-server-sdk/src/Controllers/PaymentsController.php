<?php

declare(strict_types=1);

/*
 * PaypalServerSdkLib
 *
 * This file was automatically generated by APIMATIC v3.0 ( https://www.apimatic.io ).
 */

namespace PaypalServerSdkLib\Controllers;

use Core\Request\Parameters\BodyParam;
use Core\Request\Parameters\HeaderParam;
use Core\Request\Parameters\TemplateParam;
use Core\Response\Types\ErrorType;
use CoreInterfaces\Core\Request\RequestMethod;
use PaypalServerSdkLib\Exceptions\ErrorException;
use PaypalServerSdkLib\Http\ApiResponse;
use PaypalServerSdkLib\Models\CapturedPayment;
use PaypalServerSdkLib\Models\PaymentAuthorization;
use PaypalServerSdkLib\Models\Refund;

class PaymentsController extends BaseController
{
    /**
     * Shows details for an authorized payment, by ID.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function authorizationsGet(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::GET, '/v2/payments/authorizations/{authorization_id}')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('authorization_id', $options)->extract('authorizationId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(PaymentAuthorization::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Captures an authorized payment, by ID.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function authorizationsCapture(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(
            RequestMethod::POST,
            '/v2/payments/authorizations/{authorization_id}/capture'
        )
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('authorization_id', $options)->extract('authorizationId'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'The request failed because it is not well-formed or is syntactically incor' .
                    'rect or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The request failed because the caller has insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn(
                '409',
                ErrorType::init(
                    'The server has detected a conflict while processing this request.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The request failed because it is semantically incorrect or failed business validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(CapturedPayment::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Reauthorizes an authorized PayPal account payment, by ID. To ensure that funds are still available,
     * reauthorize a payment after its initial three-day honor period expires. Within the 29-day
     * authorization period, you can issue multiple re-authorizations after the honor period expires.
     * <br/><br/>If 30 days have transpired since the date of the original authorization, you must create
     * an authorized payment instead of reauthorizing the original authorized payment.<br/><br/>A
     * reauthorized payment itself has a new honor period of three days.<br/><br/>You can reauthorize an
     * authorized payment from 4 to 29 days after the 3-day honor period. The allowed amount depends on
     * context and geography, for example in US it is up to 115% of the original authorized amount, not to
     * exceed an increase of $75 USD.<br/><br/>Supports only the `amount` request parameter.
     * <blockquote><strong>Note:</strong> This request is currently not supported for Partner use cases.
     * </blockquote>
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function authorizationsReauthorize(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(
            RequestMethod::POST,
            '/v2/payments/authorizations/{authorization_id}/reauthorize'
        )
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('authorization_id', $options)->extract('authorizationId'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'The request failed because it is not well-formed or is syntactically incor' .
                    'rect or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The request failed because it either is semantically incorrect or failed b' .
                    'usiness validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(PaymentAuthorization::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Voids, or cancels, an authorized payment, by ID. You cannot void an authorized payment that has been
     * fully captured.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function authorizationsVoid(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(
            RequestMethod::POST,
            '/v2/payments/authorizations/{authorization_id}/void'
        )
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('authorization_id', $options)->extract('authorizationId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The request failed because the caller has insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn(
                '409',
                ErrorType::init(
                    'The request failed because a previous call for the given resource is in progress.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The request failed because it either is semantically incorrect or failed b' .
                    'usiness validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->nullableType()
            ->type(PaymentAuthorization::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Shows details for a captured payment, by ID.
     *
     * @param string $captureId The PayPal-generated ID for the captured payment for which to show
     *        details.
     *
     * @return ApiResponse Response from the API call
     */
    public function capturesGet(string $captureId): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::GET, '/v2/payments/captures/{capture_id}')
            ->auth('Oauth2')
            ->parameters(TemplateParam::init('capture_id', $captureId));

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The request failed because the caller has insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(CapturedPayment::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Refunds a captured payment, by ID. For a full refund, include an empty payload in the JSON request
     * body. For a partial refund, include an <code>amount</code> object in the JSON request body.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function capturesRefund(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::POST, '/v2/payments/captures/{capture_id}/refund')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('capture_id', $options)->extract('captureId'),
                HeaderParam::init('Content-Type', 'application/json'),
                HeaderParam::init('PayPal-Request-Id', $options)->extract('paypalRequestId'),
                HeaderParam::init('Prefer', $options)->extract('prefer', 'return=minimal'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion'),
                BodyParam::init($options)->extract('body')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '400',
                ErrorType::init(
                    'The request failed because it is not well-formed or is syntactically incor' .
                    'rect or violates schema.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The request failed because the caller has insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn(
                '409',
                ErrorType::init(
                    'The request failed because a previous call for the given resource is in progress.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '422',
                ErrorType::init(
                    'The request failed because it either is semantically incorrect or failed b' .
                    'usiness validation.',
                    ErrorException::class
                )
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Refund::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }

    /**
     * Shows details for a refund, by ID.
     *
     * @param array $options Array with all options for search
     *
     * @return ApiResponse Response from the API call
     */
    public function refundsGet(array $options): ApiResponse
    {
        $_reqBuilder = $this->requestBuilder(RequestMethod::GET, '/v2/payments/refunds/{refund_id}')
            ->auth('Oauth2')
            ->parameters(
                TemplateParam::init('refund_id', $options)->extract('refundId'),
                HeaderParam::init('PayPal-Auth-Assertion', $options)->extract('paypalAuthAssertion')
            );

        $_resHandler = $this->responseHandler()
            ->throwErrorOn(
                '401',
                ErrorType::init(
                    'Authentication failed due to missing authorization header, or invalid auth' .
                    'entication credentials.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '403',
                ErrorType::init(
                    'The request failed because the caller has insufficient permissions.',
                    ErrorException::class
                )
            )
            ->throwErrorOn(
                '404',
                ErrorType::init('The request failed because the resource does not exist.', ErrorException::class)
            )
            ->throwErrorOn('500', ErrorType::init('The request failed because an internal server error occurred.'))
            ->throwErrorOn('0', ErrorType::init('The error response.', ErrorException::class))
            ->type(Refund::class)
            ->returnApiResponse();

        return $this->execute($_reqBuilder, $_resHandler);
    }
}