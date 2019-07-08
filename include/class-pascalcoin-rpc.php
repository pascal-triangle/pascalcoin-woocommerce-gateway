<?php
/**
 *
 * Base class for implementing RPC calls
 *
 * @author     mosu-forge
 * @copyright  2018
 * @license    MIT
 *
 */

class Pascalcoin_Rpc
{
    private $ch;
    protected $id = 0;

    public function __construct($host='127.0.0.1', $port='4003', $protocol='http')
    {
        if (!extension_loaded('curl'))
            throw new \ErrorException('cURL library is not loaded');
        if (!extension_loaded('json'))
            throw new \ErrorException('JSON library is not loaded');

        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_URL, "{$protocol}://{$host}:{$port}/json_rpc");

        if($protocol != 'https')
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    protected function exec($method, $params)
    {
        $this->id++;

        if(!is_string($method))
            $this->return_error('invalid method');
        if(!is_array($params))
            $this->return_error('invalid parameters');

        if(empty($params))
            $request = json_encode(['jsonrpc'=>'2.0','method'=>$method,'id'=>$this->id]);
        else
            $request = json_encode(['jsonrpc'=>'2.0','method'=>$method,'params'=>$params,'id'=>$this->id]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request);

        $response = curl_exec($this->ch);

        if(curl_errno($this->ch))
            $this->return_error(curl_error($this->ch));

        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if($http_code != 200)
            $this->return_error($this->http_status_text($http_code));

        $response_json = json_decode($response);

        $json_error = json_last_error_msg();

        if($json_error != 'No error')
            $this->return_error($json_error);

        return $response_json;

    }

    protected function http_status_text($http_code = -1)
    {
        switch ($http_code) {
        case 100: return 'Continue';
        case 101: return 'Switching Protocols';
        case 200: return 'OK';
        case 201: return 'Created';
        case 202: return 'Accepted';
        case 203: return 'Non-Authoritative Information';
        case 204: return 'No Content';
        case 205: return 'Reset Content';
        case 206: return 'Partial Content';
        case 300: return 'Multiple Choices';
        case 301: return 'Moved Permanently';
        case 302: return 'Moved Temporarily';
        case 303: return 'See Other';
        case 304: return 'Not Modified';
        case 305: return 'Use Proxy';
        case 400: return 'Bad Request';
        case 401: return 'Unauthorized';
        case 402: return 'Payment Required';
        case 403: return 'Forbidden';
        case 404: return 'Not Found';
        case 405: return 'Method Not Allowed';
        case 406: return 'Not Acceptable';
        case 407: return 'Proxy Authentication Required';
        case 408: return 'Request Time-out';
        case 409: return 'Conflict';
        case 410: return 'Gone';
        case 411: return 'Length Required';
        case 412: return 'Precondition Failed';
        case 413: return 'Request Entity Too Large';
        case 414: return 'Request-URI Too Large';
        case 415: return 'Unsupported Media Type';
        case 500: return 'Internal Server Error';
        case 501: return 'Not Implemented';
        case 502: return 'Bad Gateway';
        case 503: return 'Service Unavailable';
        case 504: return 'Gateway Time-out';
        case 505: return 'HTTP Version not supported';
        default:
            return 'Unknown http status code "' . htmlentities($http_code) . '"';
        }
    }

    
    protected function return_error($message = '', $code = -1)
    {
        return (object) [
            'error' => (object) [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $this->id,
            'jsonrpc' => '2.0',
        ];
    }

    public function getblockcount()
    {
        return $this->exec(__FUNCTION__, is_object(@func_get_arg(0)) ? func_get_arg(0) : (object) get_defined_vars());
    }
    
    public function getaccount($account=0)
    {
        return $this->exec(__FUNCTION__, is_object(@func_get_arg(0)) ? func_get_arg(0) : (object) get_defined_vars());
    }
    
    public function getaccountoperations($account=0, $depth=100, $start=0, $max=100)
    {
        return $this->exec(__FUNCTION__, is_object(@func_get_arg(0)) ? func_get_arg(0) : (object) get_defined_vars());
    }


    // Helper function to get incoming payments filtered by payload
    public function get_all_payments($account=0, $payment_id="")
    {
        $ops = $this->getaccountoperations($account, 100, -1, 100);
        $payments = array();
        if(!isset($ops->result)) {
            return $payments;
        }
        foreach($ops->result as $op) {
            if($op->optype != 1) continue;
            if($op->dest_account != $account) continue;
            if(strtolower(hex2bin($op->payload)) == $payment_id) {
                // store the payment amount in atomic units for mysql 
                $payment['amount'] = intval($op->amount * PASCALCOIN_GATEWAY_ATOMIC_UNITS_POW);
                $payment['tx_hash'] = $op->ophash;
                $payment['block_height'] = $op->block;
                $payments[] = $payment;
            }
        }
        return $payments;
    }
}
