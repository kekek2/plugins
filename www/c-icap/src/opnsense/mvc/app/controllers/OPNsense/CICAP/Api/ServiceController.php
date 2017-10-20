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

namespace OPNsense\CICAP\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;
use \OPNsense\CICAP\General;
use \OPNsense\Syslog\Syslog;

/**
 * Class ServiceController
 * @package OPNsense\CICAP
 */
class ServiceController extends ApiControllerBase
{
    private $filename = "/var/log/c-icap/server.log";

    /**
     * check if ClamAV plugin is installed
     * @return array
     */
    public function checkclamavAction()
    {
        $backend = new Backend();
        $mdlGeneral = new General();
        $response = $backend->configdRun("firmware plugin clamav");
        return $response;
    }

    /**
     * start cicap service (in background)
     * @return array
     */
    public function startAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("cicap start");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * stop cicap service
     * @return array
     */
    public function stopAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("cicap stop");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * restart cicap service
     * @return array
     */
    public function restartAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $response = $backend->configdRun("cicap restart");
            return array("response" => $response);
        } else {
            return array("response" => array());
        }
    }

    /**
     * retrieve status of cicap
     * @return array
     * @throws \Exception
     */
    public function statusAction()
    {
        $backend = new Backend();
        $mdlGeneral = new General();
        $response = $backend->configdRun("cicap status");

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
     * reconfigure cicap, generate config and reload
     */
    public function reconfigureAction()
    {
        if ($this->request->isPost()) {
            // close session for long running action
            $this->sessionClose();

            $mdlGeneral = new General();
            $backend = new Backend();

            $runStatus = $this->statusAction();

            // stop cicap if it is running or not
            $this->stopAction();

            // generate template
            $backend->configdRun('template reload OPNsense/CICAP');

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

                $logent = explode(",", $logent);
                if (count($logent) < 3 || $logent[0] == "" || !date_create($logent[0]))
                    continue;
                $formatted[] = array('time' => $logent[0], 'filter' => $filter, 'message' => implode(",", array_slice($logent, 2)));
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
            $backend->configdRun("cicap stop");
            $backend->configdRun("syslog clearlog {$this->filename}");
            $backend->configdRun("cicap start");

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
        $config = Config::getInstance()->object();

        $tmp = tempnam(sys_get_temp_dir(), '_log_');
        $backend = new Backend();
        $backend->configdRun("syslog dumplogtofile {$this->filename} {$tmp}");

        $this->view->disable();
        $this->response->setFileToSend($tmp, "{$config->system->hostname}-c_icap.log");
        $this->response->setContentType("text/plain","charset=utf-8");
        $this->response->send();
        unlink($tmp);
        die();
    }
}
