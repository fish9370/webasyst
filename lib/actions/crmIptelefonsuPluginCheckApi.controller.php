<?php

class crmIptelefonsuPluginCheckApiController extends crmJsonController
{
    public function execute()
    {
        $result = false;
        try {
            $api = new crmIptelefonsuPluginApi();
            $result = $api->checkApi();
        } catch (waException $e) {}

        $this->response = $result;
    }
}
