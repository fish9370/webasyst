<?php

class crmIptelefonsuPluginSettingsAction extends crmViewAction
{
    /**
     * @throws waDbException
     * @throws waException
     */
    public function execute()
    {
        $this->view->assign(array(
            'pbx_url'      => wa()->getSetting('pbx_url', '', array('crm', 'iptelefonsu')),
            'pbx_key'      => wa()->getSetting('pbx_key', '', array('crm', 'iptelefonsu')),
            'crm_key'      => wa()->getSetting('crm_key', '', array('crm', 'iptelefonsu')),
            'callback_url' => $this->getCallbackUrl(),
        ));
    }

    /**
     * @return bool|string
     * @throws waException
     */
    protected function getCallbackUrl()
    {
        $routing = wa()->getRouting()->getByApp('crm');
        if (!$routing) {
            return false;
        }
        return rtrim(wa()->getRouteUrl('crm', array(
            'plugin' => 'iptelefonsu',
            'module' => 'frontend',
            'action' => 'callback',
        ), true), '/');
    }
}
