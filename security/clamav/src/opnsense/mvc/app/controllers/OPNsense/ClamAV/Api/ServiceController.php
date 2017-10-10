<?php

/**
 *    Copyright (C) 2015 - 2017 Deciso B.V.
 *    Copyright (C) 2017 Michael Muenz
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

namespace OPNsense\ClamAV\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Core\Backend;
use \OPNsense\ClamAV\General;
use \OPNsense\Syslog\Syslog;

/**
 * Class ServiceController
 * @package OPNsense\ClamAV
 */
class ServiceController extends ApiControllerBase
{
    private $filename = "/var/log/clamav/freshclam.log";
    /**
     * load the initial signatures
     * @return array
     */
    public function freshclamAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $command = 'clamav freshclam';
            if ($this->request->hasPost('action')) {
                $command .= ' go';
            }
            $response = trim($backend->configdRun($command));
            return array('status' => $response);
        } else {
            return array('status' => 'error');
        }
    }
    /**
     * start clamav service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("clamav start", true);
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop clamav service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("clamav stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart clamav service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("clamav restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of clamav
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlGeneral = new General();
        $response = $backend->configdRun("clamav status");

        if (strpos($response, "not running") > 0) {
            if ($mdlGeneral->enabled->__toString() == 1) {
                $status = "stopped";
            } else {
                $status = "disabled";
            }
        } elseif (strpos($response, "is running") > 0) {
            $status = "running";
        } elseif ($mdlGeneral->enabled->__toString() == 0) {
            $status = "disabled";
        } else {
            $status = "unkown";
        }


        return array("status" => $status);
    }

    /**
     * reconfigure clamav, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $mdlGeneral = new General();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // stop clamav if it is running or not
            $this->stopAction();

            // generate template
            $backend->configdRun('template reload OPNsense/ClamAV');

            // (res)start daemon
            if ($mdlGeneral->enabled->__toString() == 1) {
                $this->startAction();
            }

            return array("status" => "ok");
        } else {
            return array("status" => "failed");
        }
    }

    /**
     * get ClamAV and signature versions
     */
    public function versionAction()
    {
        $infos = array(
            "clamav" => array("search" => "Version: "),
            "main" => array("search" => "main.cvd: "),
            "daily" => array("search" => "daily.cld: "),
            "bytecode" => array("search" => "bytecode.cld: "),
            "signatures" => array("search" => "Total number of signatures: ")
        );
        $backend = new Backend();
        $result = array();
        $response = $backend->configdRun("clamav version");
        if ($response != null) {
            foreach (explode("\n", $response) as $str) {
                foreach ($infos as $key => $info) {
                    if (strpos($str, $info["search"]) !== false) {
                        $version = substr($str, strlen($info["search"]));
                        if (isset($version)) {
                            $result[$key] = $version;
                        }
                    }
                }
            }
            return array("version" => $result);
        }
        else {
            return array();
        }
    }

    /**
     * clear custom log
     * @return array
     */
    public function getlogAction()
    {
        if ($this->request->isPost()) {

            $filter = $this->request->getPost('filter');

            $this->sessionClose();

            $mdl = new Syslog();
            $reverse = $mdl->Reverse->__toString();
            $numentries = intval($mdl->NumEntries->__toString());

            if(!file_exists($this->filename))
                return array("status" => "ok", "data" => array(array('time' => gettext("No data found"), 'filter' => "", 'message' => "")), 'filters' => '');

            $dump_filter = "";
            $filters = preg_split('/\s+/', trim(preg_quote($filter,'/')));
            foreach ($filters as $key => $pattern) {
                if(trim($pattern) == '')
                    continue;
                if ($key > 0)
                    $dump_filter .= "&&";
                $dump_filter .= "/$pattern/";
            }

            $logdata = array();
            $formatted = array();
            if($this->filename != '') {
                $backend = new Backend();
                $logdatastr = $backend->configdRun("syslog dumplog {$this->filename} {$numentries} {$reverse} {$dump_filter}");
                $logdata = explode("\n", $logdatastr);
            }

            foreach ($logdata as $logent) {
                if(trim($logent) == '')
                    continue;

                $logent = explode("->", $logent);
                if (count($logent) != 2 || $logent[0] == "" || !date_create($logent[0]))
                    continue;
                $formatted[] = array('time' => $logent[0], 'filter' => $filter, 'message' => $logent[1]);
            }

            if (count($formatted) == 0)
                return array("status" => "ok", "data" => array(array('time' => gettext("No data found"), 'filter' => "", 'message' => "")), 'filters' => '');

            return array("status" => "ok", "data" => $formatted, 'filters' => $filters);

        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * clear custom log
     * @return array
     */
    public function clearLogAction()
    {
        if ($this->request->isPost()) {

            $this->sessionClose();

            $backend = new Backend();
            $backend->configdRun("clamav stop");
            $backend->configdRun("syslog clearlog {$this->filename}");
            $backend->configdRun("clamav start");

            return array("status" => "ok", "message" => gettext("The log file has been reset."));
        } else {
            return array("status" => "failed", "message" => gettext("Wrong request"));
        }
    }

    /**
     * download log-file
     * @return file content
     */
    public function downloadAction()
    {
        $this->sessionClose();
        $config = Config::getInstance()->object();

        $tmp = tempnam(sys_get_temp_dir(), '_log_');
        $backend = new Backend();
        $backend->configdRun("syslog dumplogtofile {$this->filename} {$tmp}");

        $this->view->disable();
        $this->response->setFileToSend($tmp, "{$config->system->hostname}-freshclam.log");
        $this->response->setContentType("text/plain","charset=utf-8");
        $this->response->send();
        unlink($tmp);
        die();
    }
}
