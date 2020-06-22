<?php


class crmIptelefonsuPluginTelephony extends crmPluginTelephony
{
    public function getRecordHref($call)
    {
        return array(
            'href'    => 'javascript:void('.json_encode($call['id']).');',
            'onclick' => 'iptelefonsuHandleDownload(event,this,'.json_encode(array(
                    'call' => $call['id'],
                )).')',
        );
    }

    public function getNumbers()
    {
        $pbx_users = $this->getApi()->getPbxUsers();
        if (empty($pbx_users)) {
            return array();
        }

        $this->getPbxModel()->deleteByField(
            array(
                'plugin_id' => 'iptelefonsu',
            )
        );

        $this->getPbxModel()->multipleInsert(
            array(
                'plugin_id'          => 'iptelefonsu',
                'plugin_user_number' => array_keys($pbx_users),
            )
        );

        return $pbx_users;
    }

    public function isInitCallAllowed()
    {
        return true;
    }

    public function initCall($number_from, $number_to, $call)
    {
        $this->getApi()->initCall($number_from, $number_to, $call);
    }

    /**
     * @return crmIptelefonsuPluginApi
     * @throws waException
     */
    protected function getApi()
    {
        return new crmIptelefonsuPluginApi();
    }

}
