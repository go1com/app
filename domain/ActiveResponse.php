<?php

namespace go1\app\domain;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveResponse extends Response
{
    private $jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    private $terminate   = [];
    private $accepts     = [];

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function setJsonOptions(int $options)
    {
        $this->jsonOptions = $options;
    }

    public function terminate(callable $callback)
    {
        $this->terminate[] = $callback;

        return $this;
    }

    /**
     * @return callable[]
     */
    public function terminateCallbacks(): array
    {
        return $this->terminate;
    }

    public function prepare(Request $req)
    {
        parent::prepare($req);

        $this->accepts = $req->headers->get('accept', '');
        $this->accepts = explode(', ', $this->accepts);
        foreach ($this->accepts as &$accept) {
            $accept = trim($accept);
            switch ($accept) {
                case 'application/x-msgpack':
                    if (!function_exists('msgpack_pack')) {
                        continue;
                    }

                    # No break;

                case 'application/json':
                    $this->headers->set('Content-Type', $accept);

                    return $this;
            }
        }

        return $this;
    }

    public function getContent()
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        foreach ($this->accepts as &$accept) {
            switch (trim($accept)) {
                case 'application/x-msgpack':
                    if (function_exists('msgpack_pack')) {
                        return msgpack_pack($this->content);
                    }
                    break;

                case 'application/json':
                    return json_encode($this->content, $this->jsonOptions);
            }
        }

        return json_encode($this->content, $this->jsonOptions);
    }
}
