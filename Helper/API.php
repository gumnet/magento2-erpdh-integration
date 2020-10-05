<?php
/**
 * @author Gustavo Ulyssea - gustavo.ulyssea@gmail.com
 * @copyright Copyright (c) 2020 GumNet (https://gum.net.br)
 * @package GumNet ErpDh
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY GUM Net (https://gum.net.br). AND CONTRIBUTORS
 * ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 * TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE FOUNDATION OR CONTRIBUTORS
 * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace GumNet\ErpDh\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;

class API extends AbstractHelper
{
    protected $url;
    protected $_logger;
    protected $_scopeConfig;
    protected $_storeManager;
    protected $_dbErpDh;

    public function __construct(\Psr\Log\LoggerInterface $logger,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Magento\Store\Model\StoreManagerInterface $storeManager,
                                \GumNet\ErpDh\Helper\DbErpDh $dbErpDh
    )
    {
        $this->_scopeConfig = $scopeConfig;
        $this->url = "https://apiame.gum.net.br/erpdh";
        $this->_logger = $logger;
        $this->_storeManager = $storeManager;
        $this->_dbErpDh = $dbErpDh;
    }
    /*
     * {
    "customer": {
        "name": "Nome completo do cliente",
        "cpf_cnpj": "000.000.000-00 (obrigatório se PF)",
        "telephone": "(00) 0000-00000",
        "cnpj": "00.000.000/0000-00 (obrigatório se PJ)",
        "razao_social": "Razão Social da Empresa (obrigatório se PJ)",
        "nome_fantasia": "Nome Fantasia da Empresa (obrigatório se PJ)",
        "ie": "00000000 (opcional)",
        "dob": "00/00/0000 (opcional)"
    },
    "shipping_address": {
        "street": "Rua abc",
        "number": "123",
        "additional": "Complemento (opcional)",
        "neighborhood": "Bairro",
        "city": "Cidade",
        "city_ibge_code": "000000",
        "uf": "UF",
        "country": "BR"
    },
    "items":[
        {
            "sku": "Código SKU",
            "name": "Nome do produto",
            "price": "Preço do item na venda",
            "qty": "Quantidade vendida"
        }
    ],
    "shipping_method": "Código do método de entrega",
    "payment_method": "Código do método de pagamento",
    "payment_installments": "Número de parcelas (opcional)"
    "subtotal": 12356.78,
    "shipping_amount": 123.45,
    "discount": 0,
    "total": 123456,78,
}
     *
     */

    public function createOrder(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        if(!$this->_scopeConfig->getValue('erpdh/general/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) return;

        $url = $this->url . "/webhook/sales";

        $customer['name'] = $order->getCustomerFirstname()." ";
        if($order->getCustomerMiddlename()) {
            $customer['name'] .= $order->getCustomerMiddlename() . " ";
        }
        $customer['name'] .= $order->getCustomerLastname();
        $customer['cpf_cnpj'] = $order->getCustomerTaxvat();
        $customer['telephone'] = $order->getShippingAddress()->getTelephone();
        if($order->getCustomerDob()){
            $customer['dob'] = $order->getCustomerDob();
        }
        $json_array['customer'] = $customer;


        $street_line = $this->_scopeConfig->getValue('erpdh/address/street', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $address['street'] = $order->getShippingAddress()->getStreet()[$street_line];

        $number_line = $this->_scopeConfig->getValue('erpdh/address/number', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $address['number'] = $order->getShippingAddress()->getStreet()[$number_line];

        $additional_line = $this->_scopeConfig->getValue('erpdh/address/additional', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if(array_key_exists($additional_line,$order->getShippingAddress()->getStreet())) {
            $address['additional_line'] = $order->getShippingAddress()->getStreet()[$additional_line];
        }

        $neighborhood_line = $this->_scopeConfig->getValue('erpdh/address/neighborhood', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $address['neighborhood'] = $order->getShippingAddress()->getStreet()[$neighborhood_line];

        $address['city'] = $order->getShippingAddress()->getCity();
        $address['uf'] = $this->codigoUF($order->getShippingAddress()->getRegion());
//        $json_array['postalCode'] = $order->getShippingAddress()->getPostcode();
        $address['country'] = "BR";
        $json_array['address'] = $address;

        $items1 = [];
        $items = $order->getAllItems();
        foreach ($items as $item) {
            if (isset($array_items)) unset($array_items);
            $array_items['sku'] = $item->getSku();
            $array_items['name'] = $item->getName();
            $array_items['price'] = $item->getRowTotal() - $item->getDiscountAmount();
            $array_items['qty'] = $item->getQtyOrdered();
            array_push($items1, $array_items);
        }
        $json_array['items'] = $items1;
        $json_array['shipping_method'] = $order->getShippingMethod();
        $json_array['payment_method'] = $order->getPayment()->getMethod();
        if($order->getPayment()->getInstallments()){
            $json_array['payment_installments'] = $order->getPayment()->getInstallments();
        }
        else{
            $json_array['payment_installments'] = "1";
        }
        $shippingAmount = $order->getShippingAmount();
        $productsAmount = $order->getGrandTotal() - $shippingAmount;
        $json_array['subtotal'] = floatval($productsAmount);
        $json_array['shipping_amount'] = floatval($shippingAmount);
        $json_array['discount'] = floatval($order->getDiscountAmount());
        $json_array['total'] = floatval($order->getGrandTotal());

        $json = json_encode($json_array);
        $result = $this->apiRequest($url, "POST", $json);

        if ($this->hasError($result, $url, $json)) return false;
        $this->_logger->info("info: " . $url . "\n" . $json);
        $result_array = json_decode($result, true);

        $this->_logger->info("info: " . $url . "\n" . $json . "\n" . $result);
        return $result;
    }
    public function hasError($result, $url, $input = "")
    {
        $result_array = json_decode($result, true);
        if (is_array($result_array)) {
            if (array_key_exists("error", $result_array)) {
                $this->_logger->info($result . "\n" . "error" . "\n" . $url . "\n" . $input);
                return true;
            }
        } else {
            $this->_logger->info("erpdhRequest hasError:" . $result);
            return false;
        }
        return false;
    }
    public function apiRequest($url, $method = "GET", $json = "")
    {
        $this->_logger->info("erpdhRequest starting...");
        $_token = $this->getToken();
        if (!$_token) return false;
        $method = strtoupper($method);
        $this->_logger->info("erpdhRequest URL:" . $url);
        $this->_logger->info("erpdhRequest METHOD:" . $method);
        if ($json) {
            $this->_logger->info("erpdhRequest JSON:" . $json);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $_token));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        $this->_logger->info("erpdhRequest OUTPUT:" . $result);
        curl_close($ch);
        return $result;
    }
    public function getToken()
    {
        $this->_logger->info("erpdhRequest getToken starting...");
        // check if existing token will be expired within 10 minutes
        if($token = $this->_dbErpDh->getToken()){
            return $token;
        }
        // get user & pass from core_config_data
        $username = $this->_scopeConfig->getValue('erpdh/general/api_user', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $password = $this->_scopeConfig->getValue('erpdh/general/api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$username || !$password) {
            $this->_logger->info("erpdhRequest: user/pass not found on db", "error", "-", "-");
            return false;
        }
        $url = $this->url . "/auth";
        $ch = curl_init();
        $post['user'] = $username;
        $this->_logger->info("erpdhRequest: ".$username);
        $post['pass'] = $password;
        $this->_logger->info("erpdhRequest: ".$password);
        $post = "pass=".$password;

        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
//        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
        ));
        $result = curl_exec($ch);
        $this->_logger->info("erpdhRequest getToken: ".$result);
        if ($this->hasError($result, $url, json_encode($post))) return false;
        $result_array = json_decode($result, true);
        if(!array_key_exists('access_token',$result_array)) return false;
        $this->_logger->info($result . "\n" . $url . "\n" .  $username . ":" . $password);

        $expires_in = (int)time() + intval($result_array['expires_in']);
        $this->_dbErpDh->updateToken($expires_in,$result_array['access_token']);
        $this->_logger->info("erpdhRequest token: ".$result_array['access_token']);
        return $result_array['access_token'];
    }
    public function codigoUF($txt_uf)
    {
        $array_ufs = array("Rondônia" => "RO",
            "Acre" => "AC",
            "Amazonas" => "AM",
            "Roraima" => "RR",
            "Pará" => "PA",
            "Amapá" => "AP",
            "Tocantins" => "TO",
            "Maranhão" => "MA",
            "Piauí" => "PI",
            "Ceará" => "CE",
            "Rio Grande do Norte" => "RN",
            "Paraíba" => "PB",
            "Pernambuco" => "PE",
            "Alagoas" => "AL",
            "Sergipe" => "SE",
            "Bahia" => "BA",
            "Minas Gerais" => "MG",
            "Espírito Santo" => "ES",
            "Rio de Janeiro" => "RJ",
            "São Paulo" => "SP",
            "Paraná" => "PR",
            "Santa Catarina" => "SC",
            "Rio Grande do Sul (*)" => "RS",
            "Mato Grosso do Sul" => "MS",
            "Mato Grosso" => "MT",
            "Goiás" => "GO",
            "Distrito Federal" => "DF");
        $uf = "RJ";
        foreach ($array_ufs as $key => $value) {
            if ($key == $txt_uf) {
                $uf = $value;
                break;
            }
        }
        return $uf;
    }
}
