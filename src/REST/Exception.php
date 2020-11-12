<?php
/**
 * @package REST
 * @author Denis Korenevskiy <denkoren@corp.badoo.com>
 */

namespace Mekras\Jira\REST;

class Exception extends \Mekras\Jira\Exception
{
    protected $api_response = null;

    /**
     * @return mixed
     */
    public function getApiResponse()
    {
        return $this->api_response;
    }

    /**
     * @param mixed $api_response
     * @return $this
     */
    public function setApiResponse($api_response)
    {
        $this->api_response = $api_response;
        return $this;
    }
}
