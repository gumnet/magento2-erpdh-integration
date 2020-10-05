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

class DbErpDh extends AbstractHelper
{

    protected $_connection;
    protected $_logger;

    public function __construct(\Magento\Framework\App\ResourceConnection $resource,
                                \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_connection = $resource->getConnection();
        $this->_logger = $logger;
    }
    public function updateToken($expires_in,$token)
    {
        $sql = "UPDATE erpdh_config SET erpdh_value = '" . $expires_in . "'WHERE erpdh_option = 'token_expires'";
        $this->_connection->query($sql);
        $sql = "UPDATE erpdh_config SET  erpdh_value = '" . $token . "' WHERE erpdh_option = 'token_value'";
        $this->_connection->query($sql);
    }
    public function getToken()
    {
        $sql = "SELECT erpdh_value FROM erpdh_config WHERE erpdh_option = 'token_expires'";
        $token_expires = $this->_connection->fetchOne($sql);
        $sql = "SELECT erpdh_value FROM erpdh_config WHERE erpdh_option = 'token_value'";
        if (time() + 600 < $token_expires) {
            $token = $this->_connection->fetchOne($sql);
            $this->_logger->info("erpdhRequest getToken returns: " . $token);
            return $token;
        }
        return false;
    }
}

