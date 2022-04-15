<?php

namespace Pils36\Wayapay\Http;

use \Pils36\Wayapay\Contracts\RouteInterface;
use \Pils36\Wayapay\Helpers\Router;
use \Pils36\Wayapay;

class RequestBuilder
{
    protected $wayapayObj;
    protected $interface;
    protected $request;

    public $payload = [ ];
    public $sentargs = [ ];

    public function __construct($wayapayObj, $interface, array $payload = [ ], array $sentargs = [ ])
    {
        $this->request = new Request($wayapayObj);
        $this->wayapayObj = $wayapayObj;
        $this->interface = $interface;
        $this->payload = $payload;
        $this->sentargs = $sentargs;
    }

    public function build()
    {
        $this->request->headers["wayaPublicKey"] = $this->wayapayObj->secret_key;
        $this->request->headers["User-Agent"] = "Wayapay/v1 PhpBindings/" . Wayapay::VERSION;
        $this->request->endpoint = Router::WAYAPAY_API_ROOT . $this->interface[RouteInterface::ENDPOINT_KEY];
        $this->request->method = $this->interface[RouteInterface::METHOD_KEY];
        $this->moveArgsToSentargs();
        $this->putArgsIntoEndpoint($this->request->endpoint);
        $this->packagePayload();
        return $this->request;
    }

    public function packagePayload()
    {
        if (is_array($this->payload) && count($this->payload)) {
            if ($this->request->method === RouteInterface::GET_METHOD) {
                $this->request->endpoint = $this->request->endpoint . '?' . http_build_query($this->payload);
            } else {
                $this->request->body = json_encode($this->payload);
            }
        }
    }

    public function putArgsIntoEndpoint(&$endpoint)
    {
        foreach ($this->sentargs as $key => $value) {
            $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
        }
    }

    public function moveArgsToSentargs()
    {
        if (!array_key_exists(RouteInterface::ARGS_KEY, $this->interface)) {
            return;
        }
        $args = $this->interface[RouteInterface::ARGS_KEY];
        foreach ($this->payload as $key => $value) {
            if (in_array($key, $args)) {
                $this->sentargs[$key] = $value;
                unset($this->payload[$key]);
            }
        }
    }
}