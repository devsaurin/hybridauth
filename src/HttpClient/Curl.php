<?php
/*!
* HybridAuth
* http://hybridauth.github.io | http://github.com/hybridauth/hybridauth
* (c) 2015 HybridAuth authors | http://hybridauth.github.io/license.html
*/

namespace Hybridauth\HttpClient;

/**
 * HybridAuth default Http client
 */
class Curl implements HttpClientInterface
{
    /**
    * Default curl options
    *
    * These defaults options can be overwritten when sending requests.
    *
    * See setCurlOptions()
    *
    * @var array
    */
    protected $curlOptions = [
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLINFO_HEADER_OUT    => true,
        CURLOPT_USERAGENT      => 'HybridAuth Library (https://github.com/hybridauth/hybridauth)',
    ];

    /**
    * Method request() arguments
    *
    * This is used for debugging.
    *
    * @var array
    */
    protected $requestArguments = [];

    /**
    * Default request headers
    *
    * @var array
    */
    protected $requestHeader = [
        'Accept'        => '*/*',
        'Cache-Control' => 'max-age=0',
        'Connection'    => 'keep-alive',
        'Expect'        => '',
        'Pragma'        => '',
    ];

    /**
    * Raw response returned by server
    *
    * @var string
    */
    protected $responseBody = '';

    /**
    * Headers returned in the response
    *
    * @var array
    */
    protected $responseHeader = [];

    /**
    * Response HTTP status code
    *
    * @var integer
    */
    protected $responseHttpCode = 0;

    /**
    * Last curl error number
    *
    * @var mixed
    */
    protected $responseClientError = null;

    /**
    * Information about the last transfer
    *
    * @var mixed
    */
    protected $responseClientInfo = [];

    /**
    * Hybridauth logger instance
    *
    * @var object
    */
    protected $logger = null;

    /**
    * {@inheritdoc}
    */
    public function request($uri, $method = 'GET', $parameters = [], $headers = [])
    {
        $this->requestArguments = [ 'uri' => $uri, 'method' => $method, 'parameters' => $parameters, 'headers' => $headers ];

        $curl = curl_init();

        if ('GET' == $method) {
            unset($this->curlOptions[CURLOPT_POST]);
            unset($this->curlOptions[CURLOPT_POSTFIELDS]);

            $uri = $uri . (strpos($uri, '?') ? '&' : '?') . http_build_query($parameters);
        }

        if ('POST' == $method) {
            $this->curlOptions[CURLOPT_POST]       = true;
            $this->curlOptions[CURLOPT_POSTFIELDS] = $parameters;
        }

        $this->requestHeader = array_merge($this->requestHeader, $headers);

        $this->requestArguments['headers'] = $this->requestHeader;

        $this->curlOptions[CURLOPT_URL]            = $uri;
        $this->curlOptions[CURLOPT_HTTPHEADER]     = $this->prepareRequestHeaders();
        $this->curlOptions[CURLOPT_HEADERFUNCTION] = [ $this, 'fetchResponseHeader' ];

        foreach ($this->curlOptions as $opt => $value) {
            curl_setopt($curl, $opt, $value);
        }

        $response = curl_exec($curl);

        $this->responseBody        = $response;
        $this->responseHttpCode    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->responseClientError = curl_error($curl);
        $this->responseClientInfo  = curl_getinfo($curl);

        if ($this->logger) {
            $this->logger->debug("HttpClient\Curl::request( $uri, $method ), response:", $this->getResponse());

            if (false === $response) {
                $this->logger->error("HttpClient\Curl::request( $uri, $method ), curl_exec error: ", $this->responseClientError);
            }
        }

        curl_close($curl);

        return $response;
    }

    /**
    * {@inheritdoc}
    */
    public function getResponse()
    {
        $curlOptions = $this->curlOptions;

        $curlOptions[CURLOPT_HEADERFUNCTION] = '*omitted';

        return [
            'response' => [
                'code'    => $this->getResponseHttpCode(),
                'headers' => $this->getResponseHeader(),
                'body'    => $this->getResponseBody(),
            ],
            'request' => $this->getRequestArguments(),
            'client' => [
                'error' => $this->getResponseClientError(),
                'info'  => $this->getResponseClientInfo(),
                'opts'  => $curlOptions,
            ],
        ];
    }

    /**
    * Reset curl options
    *
    * @param array $curlOptions
    */
    public function setCurlOptions($curlOptions)
    {
        foreach ($curlOptions as $opt => $value) {
            $this->curlOptions[ $opt ] = $value;
        }
    }

    /**
    * Set logger instance
    *
    * @param object $logger
    */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
    * {@inheritdoc}
    */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
    * {@inheritdoc}
    */
    public function getResponseHeader()
    {
        return $this->responseHeader;
    }

    /**
    * {@inheritdoc}
    */
    public function getResponseHttpCode()
    {
        return $this->responseHttpCode;
    }

    /**
    * {@inheritdoc}
    */
    public function getResponseClientError()
    {
        return $this->responseClientError;
    }

    /**
    * @return array
    */
    protected function getResponseClientInfo()
    {
        return $this->responseClientInfo;
    }

    /**
    * Returns method request() arguments
    *
    * This is used for debugging.
    *
    * @return array
    */
    protected function getRequestArguments()
    {
        return $this->requestArguments;
    }
 
    /**
    * Fetch server response headers
    *
    * @param mixed  $curl
    * @param string $header
    *
    * @return integer
    */
    protected function fetchResponseHeader($curl, $header)
    {
        $pos = strpos($header, ':');

        if (! empty($pos)) {
            $key   = str_replace('-', '_', strtolower(substr($header, 0, $pos)));

            $value = trim(substr($header, $pos + 2));

            $this->responseHeader[ $key ] = $value;
        }

        return strlen($header);
    }

    /**
    * Convert request headers to the expect curl format
    *
    * @return array
    */
    protected function prepareRequestHeaders()
    {
        $headers = [];

        foreach ($this->requestHeader as $header => $value) {
            $headers[] = trim($header) .': '. trim($value);
        }

        return $headers;
    }
}
