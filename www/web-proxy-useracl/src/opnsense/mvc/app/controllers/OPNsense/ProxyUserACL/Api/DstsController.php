<?php

/**
 *    Copyright (C) 2019 Smart-Soft
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\ProxyUserACL\Api;

use \OPNsense\Base\ApiMutableModelControllerBase;

/**
 * Class DstsController
 * @package OPNsense\ProxyUserACL\Api
 */
class DstsController extends ApiMutableModelControllerBase
{
    static protected $internalModelName = 'Dst';
    static protected $internalModelClass = '\OPNsense\ProxyUserACL\ProxyUserACL';

    /**
     * search dsts list
     * @return array result
     * @throws \ReflectionException
     */
    public function searchDstAction()
    {
        return $this->searchBase(
            "general.Dsts.Dst",
            ['Description', 'uuid'],
            "Description"
        );
    }

    /**
     * retrieve dst settings or return defaults
     * @param string $uuid item unique id
     * @return array result
     * @throws \ReflectionException
     */
    public function getDstAction($uuid = null)
    {
        return $this->getBase("Dst", "general.Dsts.Dst", $uuid);
    }

    /**
     * update dst item
     * @param string $uuid item unique id
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function setDstAction($uuid)
    {
        return $this->setBase("Dst", "general.Dsts.Dst", $uuid);
    }

    /**
     * add new dst and set with attributes from post
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function addDstAction()
    {
        return $this->addBase("Dst", "general.Dsts.Dst");
    }

    /**
     * delete dst by uuid
     * @param string $uuid item unique id
     * @return array result status
     * @throws \Phalcon\Validation\Exception
     * @throws \ReflectionException
     */
    public function delDstAction($uuid)
    {
        foreach (["HTTPAccesses" => "HTTPAccess"] as $group => $element) {
            foreach ($this->getModel()->general->{$group}->{$element}->getChildren() as $acl) {
                if (($dsts = $acl->Dsts) != null && isset($dsts->getNodeData()[$uuid]["selected"]) && $dsts->getNodeData()[$uuid]["selected"] == 1) {
                    return ["result" => gettext("value is used")];
                }
            }
        }
        return $this->delBase("general.Dsts.Dst", $uuid);
    }

}
