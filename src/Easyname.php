<?php

/**
 * Easyname REST API client.
 *
 * @category   Easyname
 * @copyright  Copyright 2006-present Nessus GmbH (http://www.nessus.at)
 */
class Easyname
{
    const POST = 'POST';
    const GET = 'GET';
    const DEBUG = true;
    const XDEBUG_KEY = 'phpStorm';

    protected $_url;
    protected $_apiKey;
    protected $_apiAuthenticationSalt;
    protected $_apiSigningSalt;
    protected $_apiUserId;
    protected $_apiEmail;

    /**
     * Constructor loads all neccessary information from the yaml config file.
     */
    public function __construct()
    {
        $config = yaml_parse_file(__FILE__.'/../config/config.yaml');

        $this->_url = $config['url'];
        $this->_apiUserId = $config['user-id'];
        $this->_apiEmail = $config['user-email'];
        $this->_apiKey = $config['api-key'];
        $this->_apiAuthenticationSalt = $config['api-authentication-salt'];
        $this->_apiSigningSalt = $config['api-signing-salt'];
    }

    /**
     * @param string $type
     * @param string $resource
     * @param null|int $id
     * @param null $subResource
     * @param null $subId
     * @param array|null $data
     * @param null|string $perform
     * @param null|int $limit
     * @param null|int $offset
     * @return array
     */
    private function _doRequest($type, $resource, $id = null, $subResource = null, $subId = null, array $data = null, $perform = null, $limit = null, $offset = null)
    {
        $uri = '/' . $resource;
        if ($id) {
            $uri .= '/' . ((int)$id);
        }

        if ($subResource) {
            $uri .= '/' . $subResource;
        }

        if ($subId) {
            $uri .= '/' . ((int)$subId);
        }

        if ($perform) {
            $uri .= '/' . $perform;
        }

        if (self::DEBUG && self::XDEBUG_KEY) {
            $uri .= '?XDEBUG_SESSION_START=' . self::XDEBUG_KEY;
        }

        $url = $this->_url . $uri;
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($curl, CURLOPT_HTTPHEADER,
            array(
                'X-User-ApiKey:' . $this->_apiKey,
                'X-User-Authentication:' . $this->_createApiAuthentication(),
                'Accept:application/json',
                'Content-Type: application/json',
                'X-Readable-JSON:' . (self::DEBUG ? 1 : 0)
            )
        );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        if ($type === self::POST) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->_createBody($data));
        }

        $this->_debug($type . ': ' . $url);

        $response = curl_exec($curl);

        curl_close($curl);

        $this->_debug($response);

        return json_decode($response, true);
    }

    /**
     * @return string
     */
    private function _createApiAuthentication()
    {
        $authentication = base64_encode(
            md5(
                sprintf($this->_apiAuthenticationSalt, $this->_apiUserId, $this->_apiEmail)
            )
        );

        $this->_debug($authentication);

        return $authentication;
    }

    /**
     * @param array $data
     * @return string
     */
    private function _createBody(array $data = null)
    {
        if (!$data) {
            $data = array();
        }
        $timestamp = time();
        $body = array(
            'data' => $data,
            'timestamp' => $timestamp,
            'signature' => $this->_signRequest($data, $timestamp)
        );

        $this->_debug($body);

        return json_encode($body);
    }

    /**
     * @param array $data
     * @param int $timestamp
     * @return string
     */
    private function _signRequest(array $data, $timestamp)
    {
        $keys = array_merge(array_keys($data), array('timestamp'));
        sort($keys);

        $string = '';
        foreach($keys as $key) {
            if ($key !== 'timestamp') {
                $string .= (string)$data[$key];
            } else {
                $string .= (string)$timestamp;
            }
        }

        $length = strlen($string);
        $length = $length%2 == 0 ? (int)($length/2) : (int)($length/2)+1;
        $strings = str_split($string, $length);

        $signature = base64_encode(md5($strings[0] . $this->_apiSigningSalt . $strings[1]));

        $this->_debug($signature);

        return $signature;
    }

    /**
     * @param mixed $data
     */
    private function _debug($data)
    {
        if (self::DEBUG) {
            $backtrace = debug_backtrace();
            echo date('Y-m-d H:i:s') . ' - ' . $backtrace[1]['function'] . ': ';
            if (is_array($data)) {
                echo print_r($data, true) . "\n";
            } else {
                echo $data . "\n";
            }
        }
    }


    /**
     * DOMAIN
     */

    /**
     * Fetch information about a single domain.
     *
     * @param int $id
     * @return array
     */
    public function getDomain($id)
    {
        return $this->_doRequest(self::GET, 'domain', $id);
    }

    /**
     * List all active domains.
     *
     * @param null|int $limit
     * @param null|int $offset
     * @return array
     */
    public function listDomain($limit = null, $offset = null)
    {
        return $this->_doRequest(self::GET, 'domain', null, null, null, null, null, $limit, $offset);
    }

    /**
     * Register a new domain name.
     *
     * @param string $domain
     * @param int $registrantContact
     * @param int $adminContact
     * @param int $techContact
     * @param int $zoneContact
     * @param array $nameservers
     * @param bool $trustee
     * @return array
     */
    public function createDomain($domain, $registrantContact, $adminContact, $techContact, $zoneContact, $nameservers = array(), $trustee = false)
    {
        $tmpNameservers = array();
        for ($i = 0; $i < 6; $i++) {
            if ($nameservers[$i]) {
                $tmpNameservers['nameserver' . ($i+1)] = $nameservers[$i];
            }
        }

        return $this->_doRequest(
            self::POST,
            'domain',
            null,
            null,
            null,
            array_merge(
                array(
                    'domain' => $domain,
                    'registrantContact' => $registrantContact,
                    'adminContact' => $adminContact,
                    'techContact' => $techContact,
                    'zoneContact' => $zoneContact,
                    'trustee' => ($trustee ? 1 : 0),
                    'transferIn' => 0
                ),
                $tmpNameservers
            )
        );
    }

    /**
     * Transfer an existing domain name.
     *
     * @param string $domain
     * @param int $registrantContact
     * @param int $adminContact
     * @param int $techContact
     * @param int $zoneContact
     * @param array $nameservers
     * @param bool $trustee
     * @param null $transferAuthcode
     * @return array
     */
    public function transferDomain($domain, $registrantContact, $adminContact, $techContact, $zoneContact, $nameservers = array(), $trustee = false, $transferAuthcode = null)
    {
        $tmpNameservers = array();
        for ($i = 0; $i < 6; $i++) {
            if ($nameservers[$i]) {
                $tmpNameservers['nameserver' . ($i+1)] = $nameservers[$i];
            }
        }

        $tmpTransferAuthcode = array();
        if ($transferAuthcode) {
            $tmpTransferAuthcode['transferAuthcode'] = $transferAuthcode;
        }

        return $this->_doRequest(
            self::POST,
            'domain',
            null,
            null,
            null,
            array_merge(
                array(
                    'domain' => $domain,
                    'registrantContact' => $registrantContact,
                    'adminContact' => $adminContact,
                    'techContact' => $techContact,
                    'zoneContact' => $zoneContact,
                    'trustee' => ($trustee ? 1 : 0),
                    'transferIn' => 1
                ),
                $tmpNameservers,
                $tmpTransferAuthcode
            )
        );
    }

    /**
     * Delete a specific domain instantly.
     *
     * @param int $id
     * @return array
     */
    public function deleteDomain($id)
    {
        return $this->_doRequest(self::POST, 'domain', $id, null, null, null, 'delete');
    }

    /**
     * Re-purchase a previously deleted domain.
     *
     * @param int $id
     * @return array
     */
    public function restoreDomain($id)
    {
        return $this->_doRequest(self::POST, 'domain', $id, null, null, null, 'restore');
    }

    /**
     * Set an active domain to be deleted on expiration.
     *
     * @param int $id
     * @return array
     */
    public function expireDomain($id)
    {
        return $this->_doRequest(self::POST, 'domain', $id, null, null, null, 'expire');
    }

    /**
     * Undo a previously commited expire command.
     *
     * @param int $id
     * @return array
     */
    public function unexpireDomain($id)
    {
        return $this->_doRequest(self::POST, 'domain', $id, null, null, null, 'unexpire');
    }

    /**
     * Change the owner of an active domain.
     *
     * @param int $id
     * @param int $registrantContact
     * @return array
     */
    public function changeOwnerOfDomain($id, $registrantContact)
    {
        return $this->_doRequest(self::POST, 'domain', $id, null, null, array('registrantContact' => $registrantContact), 'ownerchange');
    }

    /**
     * Change additional contacts of an active domain.
     *
     * @param int $id
     * @param int $adminContact
     * @param int $techContact
     * @param int $zoneContact
     * @return array
     */
    public function changeContactOfDomain($id, $adminContact, $techContact, $zoneContact)
    {
        return $this->_doRequest(
            self::POST,
            'domain',
            $id,
            null,
            null,
            array(
                'adminContact' => $adminContact,
                'techContact' => $techContact,
                'zoneContact' => $zoneContact
            ),
            'contactchange'
        );
    }

    /**
     * Change the nameserver settings of a domain.
     *
     * @param int $id
     * @param array $nameservers
     * @return array
     */
    public function changeNameserverOfDomain($id, $nameservers = array())
    {
        $tmpNameservers = array();
        for ($i = 0; $i < 6; $i++) {
            if ($nameservers[$i]) {
                $tmpNameservers['nameserver' . ($i+1)] = $nameservers[$i];
            }
        }

        return $this->_doRequest(
            self::POST,
            'domain',
            $id,
            null,
            null,
            $tmpNameservers,
            'nameserverchange'
        );
    }


    /**
     * CONTACT
     */

    /**
     * Fetch information about a contact.
     *
     * @param int $id
     * @return array
     */
    public function getContact($id)
    {
        return $this->_doRequest(self::GET, 'contact', $id);
    }

    /**
     * List all contacts.
     *
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function listContact($limit = null, $offset = null)
    {
        return $this->_doRequest(self::GET, 'contact', null, null, null, null, null, $limit, $offset);
    }

    /**
     * Create a contact.
     *
     * @param string $type
     * @param string $alias
     * @param string $name
     * @param string $address
     * @param string $zip
     * @param string $city
     * @param string $country
     * @param string $phone
     * @param string $email
     * @param array|null $additionalData
     * @return array
     */
    public function createContact($type, $alias, $name, $address, $zip, $city, $country, $phone, $email, array $additionalData = array())
    {
        return $this->_doRequest(
            self::POST,
            'contact',
            null,
            null,
            null,
            array_merge(
                array(
                    'type' => $type,
                    'alias' => $alias,
                    'name' => $name,
                    'address' => $address,
                    'zip' => $zip,
                    'city' => $city,
                    'country' => $country,
                    'phone' => $phone,
                    'email' => $email
                ),
                $additionalData
            )
        );
    }

    /**
     * Modify a specific contact.
     *
     * @param $id
     * @param $alias
     * @param $address
     * @param $zip
     * @param $city
     * @param $phone
     * @param $email
     * @param array $additionalData
     * @return array
     */
    public function updateContact($id, $alias, $address, $zip, $city, $phone, $email, array $additionalData = array())
    {
        return $this->_doRequest(
            self::POST,
            'contact',
            $id,
            null,
            null,
            array_merge(
                array(
                    'alias' => $alias,
                    'address' => $address,
                    'zip' => $zip,
                    'city' => $city,
                    'phone' => $phone,
                    'email' => $email
                ),
                $additionalData
            )
        );
    }


    /*
     * DNS
     */

    /**
     * Fetch information about a single DNS record.
     *
     * @param int $domainId
     * @param int $id
     * @return array
     */
    public function getDns($domainId, $id)
    {
        return $this->_doRequest(self::GET, 'domain', $domainId, 'dns', $id);
    }

    /**
     * List all DNS records of a specific domain.
     *
     * @param int $domainId
     * @return array
     */
    public function listDns($domainId)
    {
        return $this->_doRequest(self::GET, 'domain', $domainId, 'dns');
    }

    /**
     * Create a DNS record for a specific domain.
     *
     * @param int $domainId
     * @param string $name
     * @param string $type
     * @param string $content
     * @param int|null $priority
     * @param int|null $ttl
     * @return array
     */
    public function createDns($domainId, $name = '', $type, $content, $priority = null, $ttl = null)
    {
        return $this->_doRequest(
            self::POST,
            'domain',
            $domainId,
            'dns',
            null,
            array(
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'priority' => $priority,
                'ttl' => $ttl
            )
        );
    }

    /**
     * Modify a specific DNS record.
     *
     * @param int $domainId
     * @param int $id
     * @param string $name
     * @param string $type
     * @param string $content
     * @param int|null $priority
     * @param int|null $ttl
     * @return array
     */
    public function updateDns($domainId, $id, $name = '', $type, $content, $priority = null, $ttl = null)
    {
        return $this->_doRequest(
            self::POST,
            'domain',
            $domainId,
            'dns',
            $id,
            array(
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'priority' => $priority,
                'ttl' => $ttl
            )
        );
    }

    /**
     * Delete a specific DNS record.
     *
     * @param int $domainId
     * @param int $id
     * @return array
     */
    public function deleteDns($domainId, $id)
    {
        return $this->_doRequest(self::POST, 'domain', $domainId, 'dns', $id, null, 'delete');
    }


    /*
     * Database
     */

    /**
     * Fetch information about a single database.
     *
     * @param int $id
     * @return array
     */
    public function getDatabase($id)
    {
        return $this->_doRequest(self::GET, 'database', $id);
    }

    /**
     * Fetch a list of all databases.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listDatabase($limit = null, $offset = null)
    {
        return $this->_doRequest(self::GET, 'database', null, null, null, null, null, $limit, $offset);
    }


    /*
     * FTP
     */

    /**
     * Fetch information about a single FTP-account.
     *
     * @param int $id
     * @return array
     */
    public function getFtpAccount($id)
    {
        return $this->_doRequest(self::GET, 'ftp-account', $id);
    }

    /**
     * Fetch a list of all FTP-accounts.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listFtpAccount($limit = null, $offset = null)
    {
        return $this->_doRequest(self::GET, 'ftp-account', null, null, null, null, null, $limit, $offset);
    }
}