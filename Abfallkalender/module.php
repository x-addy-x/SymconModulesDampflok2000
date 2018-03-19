<?
    // Klassendefinition
    class Abfallkalender extends IPSModule {

        public function Destroy() {
            //Never delete this line!
            parent::Destroy();
        }

        public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyBoolean("cbxGS", true);
            $this->RegisterPropertyBoolean("cbxHM", true);
            $this->RegisterPropertyBoolean("cbxPP", true);
            $this->RegisterPropertyBoolean("cbxBO", false);
            $this->RegisterVariableString("RestTimesHTML", "Abfalltermine", "~HTMLBox");
            $this->RegisterPropertyBoolean("cbxPush", false);
            $this->RegisterPropertyBoolean("cbxMail", false);
            $this->RegisterPropertyInteger("PushInstanceID", 0);
            $this->RegisterPropertyInteger("MailInstanceID", 0);

            //Activate timers
            $this->RegisterCyclicTimer("UpdateTimer", 0, 1, 7, 'AFK_UpdateWasteTimes('.$this->InstanceID.');');
            $this->RegisterCyclicTimer("NotificationTimer", 19, 50, 7, 'AFK_UpdateWasteTimes('.$this->InstanceID.');');
            //$this->RegisterTimer("UpdateTimer1", 0,'AFK_UpdateWasteTimes('.$this->InstanceID.');');
            $eId1 = IPS_GetEventIDByName("UpdateTimer", $this->InstanceID);
            $eId2 = IPS_GetEventIDByName("NotificationTimer", $this->InstanceID);
            IPS_SetEventCyclic($eId1, 2, 0, 0, 0, 0, 0);
            IPS_SetEventActive($eId1, true);
            IPS_SetEventCyclic($eId2, 2, 0, 0, 0, 0, 0);
            IPS_SetEventActive($eId2, true);
		}

        public function ApplyChanges() {

            //Never delete this line!
            parent::ApplyChanges();

            //$ModulInfo = IPS_GetInstance($this->InstanceID);
            //$ModulName = $ModulInfo['ModuleInfo']['ModuleName'];
            
            //$AScriptID = $this->ReadPropertyInteger("AScriptID");
            //$this->SendDebug($ModulName, "ASCriptID:".$AScriptID , 0);

            If ($this->ReadPropertyBoolean("cbxGS"))
            {
                $this->RegisterVariableString("YellowBagTimes", "Gelber Sack", "~TextBox");
                $this->EnableAction("YellowBagTimes");
            }
            Else
            {
                $this->UnregisterVariable("YellowBagTimes");
            }
            If ($this->ReadPropertyBoolean("cbxHM"))
            {
                $this->RegisterVariableString("WasteTimes", "Hausmüll", "~TextBox");
                $this->EnableAction("WasteTimes");
            }
            Else
            {
                $this->UnregisterVariable("WasteTimes");
            }
            If ($this->ReadPropertyBoolean("cbxPP"))
            {
                $this->RegisterVariableString("PaperTimes", "Pappe", "~TextBox");
                $this->EnableAction("PaperTimes");
            }
            Else
            {
                $this->UnregisterVariable("PaperTimes");
            }

            If ($this->ReadPropertyBoolean("cbxBO"))
            {
                $this->RegisterVariableString("BioTimes", "Biomüll", "~TextBox");
                $this->EnableAction("BioTimes");
            }
            Else
            {
                $this->UnregisterVariable("BioTimes");
            }

        }

        public function RequestAction($Ident, $Value)
        {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }

        public function UpdateWasteTimes()
        {
            $this->SetStatus(102);
            $ModulInfo = IPS_GetInstance($this->InstanceID);
            $ModulName = $ModulInfo['ModuleInfo']['ModuleName'];

            $this->SendDebug($ModulName, "Starting updates of waste times." , 0);
            //Settings-Variablen:
            $PushInstanceID = $this->ReadPropertyInteger("PushInstanceID");
            $MailInstanceID = $this->ReadPropertyInteger("MailInstanceID");
            $PushIsActive = $this->ReadPropertyBoolean("cbxPush");
            $MailIsActive = $this->ReadPropertyBoolean("cbxMail");
            $TimerIDForPush = $this->GetIDForIdent("NotificationTimer");
            $AbfallTermineHTMLID = IPS_GetObjectIDByIdent("RestTimesHTML", $this->InstanceID);
            
            function closest($dates, $findate)
            {
                $newDates = array();

                foreach($dates as $date)
                {
                    $newDates[] = new DateTime($date);
                }

                sort($newDates);
                foreach ($newDates as $a)
                {
                    if ($a >= $findate)
                        return $a;
                }
                return end($newDates);
            }
            //Check if NotificationTimer was triggered:
            If ($_IPS['SENDER'] == "TimerEvent")
            {
                $this->SendDebug($ModulName, "EventID ". $_IPS['EVENT'] . " has triggered.", 0);
                $TimerTriggerID = $_IPS['EVENT'];
                If ($TimerTriggerID <> $TimerIDForPush)
                {
                    $PushIsActive = false;
                    $MailIsActive = false;
                }
            }

            //Hole Abfalldaten:
            $strGS = @GetValueString(IPS_GetObjectIDByIdent("YellowBagTimes", $this->InstanceID));
            $strHM = @GetValueString(IPS_GetObjectIDByIdent("WasteTimes", $this->InstanceID));
            $strPP = @GetValueString(IPS_GetObjectIDByIdent("PaperTimes", $this->InstanceID));
            $strBO = @GetValueString(IPS_GetObjectIDByIdent("BioTimes", $this->InstanceID));
            
            If ((empty($strGS) && $this->ReadPropertyBoolean("cbxGS")) || (empty($strHM) && $this->ReadPropertyBoolean("cbxHM")) || (empty($strPP)) && $this->ReadPropertyBoolean("cbxPP") || (empty($strBO)) && $this->ReadPropertyBoolean("cbxBO"))
            {
                $this->SetStatus(201);
                $this->SendDebug($ModulName, "One or more of the waste time stings are empty!", 0);
                exit;
            }

            $today = new DateTime('today midnight');
            $now = new DateTime();
            $PushDiffTimeInterval = $now->diff($today);
            $PushDiffHours = $PushDiffTimeInterval->format('%h');
            
            $nextTermine = array();

            If ($this->ReadPropertyBoolean("cbxGS")) {
                $arrGS = explode("\n", $strGS);
                $nextTermine['Gelber Sack'] = closest($arrGS, new DateTime('today midnight'));
            }
            If ($this->ReadPropertyBoolean("cbxHM")) {
                $arrHM = explode("\n", $strHM);
                $nextTermine['Hausmüll'] = closest($arrHM, new DateTime('today midnight'));
            }
            If ($this->ReadPropertyBoolean("cbxPP")) {
                $arrPP = explode("\n", $strPP);
                $nextTermine['Pappe'] = closest($arrPP, new DateTime('today midnight'));
            }
            If ($this->ReadPropertyBoolean("cbxBO")) {
                $arrBO = explode("\n", $strBO);
                $nextTermine['Bio'] = closest($arrBO, new DateTime('today midnight'));
            }
            
            asort($nextTermine);
            
            $HTMLBox = "<table cellspacing='10'>";
            foreach ($nextTermine as $key => $value)
            {
                $HTMLBox.= "<tr><td>".$key . ":</td><td>";
                $interval = $value->diff($today);
                $days = $interval->format('%d');
                If ($days == 1)
                {
                    $HTMLBox.= "<font color=#ff8000>MORGEN</b></td></tr>";
                    If ($PushIsActive)
                    {
                        $this->SendDebug($ModulName, "Push notification is sending now.", 0);
                        WFC_PushNotification($PushInstanceID, $ModulName, "Morgen wird ".$key." abgeholt!", "", 0);
                    }
                    If ($MailIsActive)
                    {
                        $this->SendDebug($ModulName, "Mail notification is sending now.", 0);
                        SMTP_SendMail($MailInstanceID, $ModulName, "Morgen wird ".$key." abgeholt!");
                    }
                }
                ElseIf ($days == 0)
                {
                    $HTMLBox.= "<font color=#ff0000>HEUTE!</b></td></tr>";
                }
                Else
                {
                    $HTMLBox.= $value->format('d.m.Y') . "</td></tr>";
                }
            }
            $HTMLBox.= "</table>";
            SetValueString($AbfallTermineHTMLID, $HTMLBox);
        }

        public function SetDemoData()
        {
            $varGSID = IPS_GetObjectIDByIdent("YellowBagTimes", $this->InstanceID);
            $varHMID = IPS_GetObjectIDByIdent("WasteTimes", $this->InstanceID);
            $varPPID = IPS_GetObjectIDByIdent("PaperTimes", $this->InstanceID);
            $varBOID = IPS_GetObjectIDByIdent("BioTimes", $this->InstanceID);

            $bolVarGS = SetValueString($varGSID,
            "04.01.2018\n17.01.2018\n31.01.2018\n14.02.2018\n28.02.2018\n14.03.2018\n28.03.2018\n11.04.2018\n25.04.2018\n09.05.2018\n24.05.2018\n06.06.2018\n20.06.2018\n04.07.2018\n18.07.2018\n01.08.2018\n15.08.2018\n29.08.2018\n12.09.2018\n26.09.2018\n10.10.2018\n24.10.2018\n07.11.2018\n21.11.2018\n05.12.2018\n19.12.2018");

            $bolVarHM = SetValueString($varHMID,
            "03.01.2018\n16.01.2018\n30.01.2018\n13.02.2018\n27.02.2018\n13.03.2018\n27.03.2018\n10.04.2018\n24.04.2018\n08.05.2018\n23.05.2018\n05.06.2018\n19.06.2018\n03.07.2018\n17.07.2018\n31.07.2018\n14.08.2018\n28.08.2018\n11.09.2018\n25.09.2018\n09.10.2018\n23.10.2018\n06.11.2018\n20.11.2018\n04.12.2018\n18.12.2018");

            $bolVarPP = SetValueString($varPPID,
            "24.01.2018\n21.02.2018\n21.03.2018\n18.04.2018\n16.05.2018\n13.06.2018\n11.07.2018\n08.08.2018\n05.09.2018\n04.10.2018\n01.11.2018\n28.11.2018\n27.12.2018");

            $bolVarBO = SetValueString($varBOID,
            "25.01.2018\n22.02.2018\n22.03.2018\n19.04.2018\n17.05.2018\n14.06.2018\n11.07.2018\n09.08.2018\n06.09.2018\n04.10.2018\n01.11.2018\n27.11.2018\n27.12.2018");

            If ($bolVarGS && $bolVarHM && $bolVarPP && $bolVarBO)
            {
                echo "Demodaten wurden erfolgreich hinterlegt.";
            }
            else {
                echo "Demodaten konnten nicht erfolgreich hinterlegt werden!";
            }
        }
        
        /**
         * Create a cyclic timer.
         *
         * @access protected
         * @param  string $ident Name and Ident of the timer.
         * @param  integer $hour Hour of the timer.
         * @param  integer $minute Minute of the timer.
         * @param  integer $second Second of the timer.
         * @param  string $script Script content of the timer.
         */
        protected function RegisterCyclicTimer($ident, $hour, $minute, $second, $script)
        {
            $id = @$this->GetIDForIdent($ident);
            $name = $ident;
            if ($id && IPS_GetEvent($id)['EventType'] <> 1)
            {
            IPS_DeleteEvent($id);
            $id = 0;
            }
            if (!$id)
            {
            $id = IPS_CreateEvent(1);
            IPS_SetParent($id, $this->InstanceID);
            IPS_SetIdent($id, $ident);
            }
            IPS_SetName($id, $name);

            IPS_SetEventScript($id, $script);

            if (!IPS_EventExists($id)) throw new Exception("Ident with name $ident is used for wrong object type");

            //IPS_SetEventCyclic($id, 0, 0, 0, 0, 0, 0);
            IPS_SetEventCyclicTimeFrom($id, $hour, $minute, $second);
            IPS_SetEventActive($id, false);
        }
        
    }
?>