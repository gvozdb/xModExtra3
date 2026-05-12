<?php
/**
 * Encrypted Vehicle for MODX Revolution 3.x
 *
 * Encrypts package contents during build and decrypts during installation.
 * Requires valid license from modstore.pro to install.
 *
 * @package xModExtra3
 * @subpackage Transport
 *
 * @see https://modstore.pro/info/api
 */

namespace xModExtra3\Transport;

use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOObjectVehicle;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

class EncryptedVehicle extends xPDOObjectVehicle
{
    /**
     * Vehicle class identifier
     *
     * @var string
     */
    public $class = 'xModExtra3\\Transport\\EncryptedVehicle';

    /**
     * Vehicle version for API compatibility
     */
    const VERSION = '3.0.0';

    /**
     * Encryption cipher
     */
    const CIPHER = 'AES-256-CBC';

    /**
     * License server URL
     */
    const LICENSE_SERVER = 'https://modstore.pro/extras/';

    /**
     * Put object into transport package with encryption
     *
     * @param xPDOTransport $transport
     * @param mixed $object
     * @param array $attributes
     */
    public function put(&$transport, &$object, $attributes = [])
    {
        parent::put($transport, $object, $attributes);

        if (defined('PKG_ENCODE_KEY') && PKG_ENCODE_KEY) {
            $this->payload['object_encrypted'] = $this->encode(
                $this->payload['object'],
                PKG_ENCODE_KEY
            );
            unset($this->payload['object']);

            if (isset($this->payload['related_objects'])) {
                $this->payload['related_objects_encrypted'] = $this->encode(
                    $this->payload['related_objects'],
                    PKG_ENCODE_KEY
                );
                unset($this->payload['related_objects']);
            }

            $transport->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Package encrypted!');
        }
    }

    /**
     * Install encrypted vehicle
     *
     * @param xPDOTransport $transport
     * @param array $options
     * @return bool
     */
    public function install(&$transport, $options)
    {
        if (!$this->decodePayloads($transport, 'install')) {
            return false;
        }

        $transport->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Package decrypted!');

        return parent::install($transport, $options);
    }

    /**
     * Uninstall encrypted vehicle
     *
     * @param xPDOTransport $transport
     * @param array $options
     * @return bool
     */
    public function uninstall(&$transport, $options)
    {
        if (!$this->decodePayloads($transport, 'uninstall')) {
            return false;
        }

        $transport->xpdo->log(xPDO::LOG_LEVEL_INFO, 'Package decrypted!');

        return parent::uninstall($transport, $options);
    }

    /**
     * Encode data with AES-256-CBC
     *
     * @param array $data Data to encrypt
     * @param string $key Encryption key
     * @return string Base64 encoded encrypted data
     */
    protected function encode($data, $key)
    {
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLen);

        $cipherRaw = openssl_encrypt(
            serialize($data),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return base64_encode($iv . $cipherRaw);
    }

    /**
     * Decode data with AES-256-CBC
     *
     * @param string $string Base64 encoded encrypted data
     * @param string $key Decryption key
     * @return mixed Decrypted data
     */
    protected function decode($string, $key)
    {
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        $encoded = base64_decode($string);

        $iv = substr($encoded, 0, $ivLen);
        $cipherRaw = substr($encoded, $ivLen);

        $decrypted = openssl_decrypt(
            $cipherRaw,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            return false;
        }

        return unserialize($decrypted);
    }

    /**
     * Decode encrypted payloads
     *
     * @param xPDOTransport $transport
     * @param string $action install|uninstall
     * @return bool
     */
    protected function decodePayloads(&$transport, $action = 'install')
    {
        $hasEncrypted = isset($this->payload['object_encrypted'])
            || isset($this->payload['related_objects_encrypted']);

        if (!$hasEncrypted) {
            return true;
        }

        $key = $this->getDecodeKey($transport, $action);

        if (!$key) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to get decryption key. Installation aborted.'
            );
            return false;
        }

        if (isset($this->payload['object_encrypted'])) {
            $decrypted = $this->decode($this->payload['object_encrypted'], $key);

            if ($decrypted === false) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    'Failed to decrypt package. Invalid key or corrupted data.'
                );
                return false;
            }

            $this->payload['object'] = $decrypted;
            unset($this->payload['object_encrypted']);
        }

        if (isset($this->payload['related_objects_encrypted'])) {
            $decrypted = $this->decode($this->payload['related_objects_encrypted'], $key);

            if ($decrypted === false) {
                $transport->xpdo->log(
                    xPDO::LOG_LEVEL_ERROR,
                    'Failed to decrypt related objects. Invalid key or corrupted data.'
                );
                return false;
            }

            $this->payload['related_objects'] = $decrypted;
            unset($this->payload['related_objects_encrypted']);
        }

        return true;
    }

    /**
     * Get decode key from modstore.pro
     *
     * @param xPDOTransport $transport
     * @param string $action install|uninstall
     * @return string|false Decryption key or false on failure
     */
    protected function getDecodeKey(&$transport, $action)
    {
        // Same-process build + install (dev workflow with install=true in
        // _build/config.inc.php): setupEncryption() already received the key
        // from modstore.pro and stored it in PKG_ENCODE_KEY. AES-256-CBC is
        // symmetric, so encode key === decode key — reuse it directly and
        // skip the API call. On a real production install PKG_ENCODE_KEY is
        // never defined → falls through to the provider-based path below.
        if (defined('PKG_ENCODE_KEY') && PKG_ENCODE_KEY) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_INFO,
                'Using in-process PKG_ENCODE_KEY (same-process build+install)'
            );
            return (string) PKG_ENCODE_KEY;
        }

        $key = false;
        $endpoint = 'package/decode/' . $action;

        /** @var modTransportPackage $package */
        $package = $transport->xpdo->getObject(modTransportPackage::class, [
            'signature' => $transport->signature,
        ]);

        if (!($package instanceof modTransportPackage)) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Transport package not found: ' . $transport->signature
            );
            return false;
        }

        /** @var modTransportProvider $provider */
        $provider = $package->getOne('Provider');

        if (!$provider) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Package provider not found. Please select modstore.pro as provider before installation.'
            );
            return false;
        }

        $provider->xpdo->setOption('contentType', 'default');

        $params = [
            'package' => $package->package_name,
            'version' => $transport->version,
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => self::VERSION,
        ];

        $response = $provider->request($endpoint, 'POST', $params);

        if ($response === false) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to connect to license server'
            );
            return false;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'License API error: HTTP ' . $statusCode
            );
            return false;
        }

        $body = (string) $response->getBody();
        $data = @simplexml_load_string($body);

        if ($data === false) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Invalid XML response from license server'
            );
            return false;
        }

        if (!empty($data->key)) {
            $key = (string) $data->key;
        } elseif (!empty($data->message)) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'License error: ' . (string) $data->message
            );
        } else {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Invalid response from license server'
            );
        }

        return $key;
    }
}
