<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * @return array
 */
function openprovidersslnew_MetaData()
{
    return array(
        'DisplayName' => 'Openprovider ssl provisioning module',
        'APIVersion' => '1.0', // Use API Version 1.0
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}

/**
 * @return array
 */
function openprovidersslnew_ConfigOptions()
{
    $products = [];
    foreach (Capsule::table('openprovidersslnew_products')->get() as $product) {
        $products[] = $product->name;
    }

    return [
        "username" => [
            "Type" => "text",
            "Size" => "25",
            "Description" => "Openprovider login",
        ],
        "password" => [
            "Type" => "password",
            "Size" => "25",
            "Description" => "Openprovider password",
        ],
        "apiUrl" => [
            "Type" => "text",
            "Size" => "60",
            "Description" => "Openprovider API URL",
        ],
        "sslApiUrl" => [
            "Type" => "text",
            "Size" => "60",
            "Description" => "SSL API URL",
        ],
        "SSL Certificate Type" => [
            "Type" => "dropdown",
            "Options" => implode(',', $products),
        ],
    ];
}

/**
 * @param array $params
 *
 * @return string
 */
function openprovidersslnew_CreateAccount($params)
{
    include __DIR__ . '/lib/opApiWrapper.php';
    $reply = null;

    try {
        $product_id = array_shift(
            Capsule::table('openprovidersslnew_products')->where('name', $params['configoption5'])->get()
        )->product_id;

        if (isset($params['configoptions']) && isset($params['configoptions']['years'])) {
            $params['period'] = $params['configoptions']['years'];
        } else {
            $params['period'] = 1;
        }

        $reply = opApiWrapper::createSslCert($params, $product_id);
    } catch (opApiException $e) {
        logModuleCall(
            'openprovidersslnew',
            'openprovidersslnew_CreateAccount',
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getFullMessage();
    }

    $pdo = null;
    try {
        $pdo = Capsule::connection()->getPdo();
        $pdo->beginTransaction();
        //todo: INSERT INTO...
        $statement = $pdo->prepare('INSERT INTO openprovidersslnew_orders (id, product_id, name, brand_name, price, currency, changed_at) VALUES (:id, :product_id, :order_id, :status, :creation_date, :activation_date, :expiration_date, :changed_at, :service_id)');
        $statement->execute([
            ':id' => null,
            ':product_id' => $product_id,
            ':order_id' => $reply['data']['id'],
            ':status' => 'REQ',
            ':creation_date' => date('Y-m-d H:i:s', time()),
            ':activation_date' => null,
            ':expiration_date' => null,
            ':changed_at' => date('Y-m-d H:i:s', time()),
            ':service_id' => $params['serviceid'],
        ]);

        $pdo->commit();
    } catch (\Exception $e) {
        $view['errorMessage'] = "Error occurred during order saving: {$e->getMessage()}";
        $pdo->rollBack();
    }

    return "success";
}

/**
 * @param array $params
 *
 * @return array|string
 */
function openprovidersslnew_ClientArea($params)
{
    include __DIR__ . '/lib/opApiWrapper.php';
    $reply = null;

    try {
        $orderId = array_shift(
            Capsule::table('openprovidersslnew_products')->where('service_id', $params['serviceid'])->get()
        )->order_id;

        $reply = opApiWrapper::generateOtpToken($params, $orderId)['token'];
    } catch (opApiException $e) {
        logModuleCall(
            'openprovidersslnew',
            'openprovidersslnew_ClientArea',
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getFullMessage();
    }

    return [
        'templatefile' => 'templates/clientarea.tpl',
        'templateVariables' => [
            'linkValue' => $params['configoption4'] . 'auth-order-otp-token?token=' . $reply['data']['token'],
            'linkName' => 'sslinhva link',
        ],
    ];
}
