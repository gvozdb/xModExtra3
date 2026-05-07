<?php
/**
 * Encrypted Vehicle for MODX Revolution 3.x
 *
 * Encrypts package contents during build and decrypts during installation.
 * Requires valid license from modstore.pro to install.
 *
 * @package YourComponent
 * @subpackage Transport
 *
 * USAGE:
 * 1. Copy this file to: core/components/YOUR_COMPONENT/src/Transport/EncryptedVehicle.php
 * 2. Change namespace below to match your component
 * 3. Update $class property with your namespace
 *
 * @see https://modstore.pro/info/api
 */

namespace YourComponent\Transport;

use MODX\Revolution\Transport\modTransportPackage;
use MODX\Revolution\Transport\modTransportProvider;
use xPDO\Transport\xPDOObjectVehicle;
use xPDO\Transport\xPDOTransport;
use xPDO\xPDO;

class EncryptedVehicle extends xPDOObjectVehicle
{
    /**
     * Vehicle class identifier
     * IMPORTANT: Change this to match your namespace!
     *
     * @var string
     */
    public $class = 'YourComponent\\Transport\\EncryptedVehicle';

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
     * Change this if using custom license server
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
            // Encrypt main object
            $this->payload['object_encrypted'] = $this->encode(
                $this->payload['object'],
                PKG_ENCODE_KEY
            );
            unset($this->payload['object']);

            // Encrypt related objects (snippets, chunks, plugins, etc.)
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
        // Check if there's encrypted content
        $hasEncrypted = isset($this->payload['object_encrypted'])
            || isset($this->payload['related_objects_encrypted']);

        if (!$hasEncrypted) {
            // Not encrypted, proceed normally
            return true;
        }

        // Get decryption key from license server
        $key = $this->getDecodeKey($transport, $action);

        if (!$key) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to get decryption key. Installation aborted.'
            );
            return false;
        }

        // Decrypt main object
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

        // Decrypt related objects
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
        $key = false;
        $endpoint = 'package/decode/' . $action;

        // Get package object
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

        // Get provider (modstore.pro)
        /** @var modTransportProvider $provider */
        $provider = $package->getOne('Provider');

        if (!$provider) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Package provider not found. Please select modstore.pro as provider before installation.'
            );
            return false;
        }

        // Prepare API request
        $provider->xpdo->setOption('contentType', 'default');

        $params = [
            'package' => $package->package_name,
            'version' => $transport->version,
            'username' => $provider->username,
            'api_key' => $provider->api_key,
            'vehicle_version' => self::VERSION,
        ];

        // Request decryption key
        $response = $provider->request($endpoint, 'POST', $params);

        // MODX3 returns PSR-7 Response or false on connection error
        if ($response === false) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'Failed to connect to license server'
            );
            return false;
        }

        // Check HTTP status code
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $transport->xpdo->log(
                xPDO::LOG_LEVEL_ERROR,
                'License API error: HTTP ' . $statusCode
            );
            return false;
        }

        // Parse XML response
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
