<?php

class crmIptelefonsuPluginBackendActions extends waActions
{
    public function getRecordLinkAction()
    {
        $call_id = waRequest::post('call', '', 'string');

        $cm = new crmCallModel();
        $call = $cm->getById($call_id);

        if ($call && isset($call['plugin_record_id']) && !empty($call['plugin_record_id'])) {
            $this->displayJson(['record_url' => $call['plugin_record_id']]);
        } else {
            waLog::log("Error fetching URL of record for call {$call_id} from API: record does not exist.", 'crm/plugins/iptelefonsu.log');
        }
    }
}
