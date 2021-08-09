<?php

namespace Codeception\Lib\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Response;

class AddRequest
{
    /**
     * @var array
     */
    protected $messages = [];

    /**
     *
     * @var boolean
     */
    protected $hasDefinedResponses = false;

    public function __invoke(ServerRequestInterface $request, $next)
    {
        if (!$this->hasDefinedResponses) {
            return $next($request);
        }
        $response = array_shift($this->messages);
        if (empty($this->messages)) {
            $this->messages[] = clone $response;
        }
        return $response;
    }
    /**
     * @return boolean
     */
    public function hasMessage()
    {
        return !empty($this->messages);
    }
    /**
     * @param integer $status
     * @param array $headers
     * @param string $content
     * @return void
     */
    public function addMessage($status,  $headers, $content)
    {
        $this->hasDefinedResponses = true;
        $this->messages[] = new Response(
            $status,
            $headers,
            $content
        );
    }
    /**
     * @return void
     */
    public function flushMessages()
    {
        $this->messages = [];
    }
}
