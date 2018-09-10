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
            $this->RegisterVariableString("RestTimesHTML", "Termine", "~HTMLBox");

            $this->RegisterPropertyInteger("PushInstanceID", 0);
            $this->RegisterPropertyInteger("MailInstanceID", 0);

            $this->RegisterPropertyInteger("IntervalUpdateTimer", 0);
            $this->RegisterPropertyInteger("IntervalNotificationTimer", 19);
            $this->RegisterPropertyInteger("IntervalUpdateTimerMinute", 1);
            $this->RegisterPropertyInteger("IntervalNotificationTimerMinute", 50);

            //Create timers
            $this->RegisterTimer("UpdateTimer", 0, 'AFK_UpdateWasteTimes('.$this->InstanceID.');');
            $this->RegisterTimer("NotificationTimer", 0, 'AFK_UpdateWasteTimes('.$this->InstanceID.');');
		}

        public function ApplyChanges() {

            //Never delete this line!
            parent::ApplyChanges();

            $ModulInfo = IPS_GetInstance($this->InstanceID);
            $ModulName = $ModulInfo['ModuleInfo']['ModuleName'];

            $HourUpdateTimer = $this->ReadPropertyInteger("IntervalUpdateTimer");
            $HourNotificationTimer = $this->ReadPropertyInteger("IntervalNotificationTimer");
            $MinuteUpdateTimer = $this->ReadPropertyInteger("IntervalUpdateTimerMinute");
            $MinuteNotificationTimer = $this->ReadPropertyInteger("IntervalNotificationTimerMinute");
            If ((($HourUpdateTimer > 23) || ($HourNotificationTimer > 23)) || (($HourUpdateTimer < 0) || ($HourNotificationTimer < 0)))
            {
                $this->SetStatus(202);
                $this->SendDebug($ModulName, "The hour of a time is wrong!", 0);
            }
            else {
                $this->SetStatus(102);
            }

            $this->SetNewTimerInterval($HourUpdateTimer.":".$MinuteUpdateTimer.":17", "UpdateTimer");
            $this->SetNewTimerInterval($HourUpdateTimer.":".$MinuteNotificationTimer.":07", "NotificationTimer");

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
                $this->RegisterVariableString("WasteTimes", "Restmüll", "~TextBox");
                $this->EnableAction("WasteTimes");
            }
            Else
            {
                $this->UnregisterVariable("WasteTimes");
            }
            If ($this->ReadPropertyBoolean("cbxPP"))
            {
                $this->RegisterVariableString("PaperTimes", "Wertstoff", "~TextBox");
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

        //Funktion für die Standardaktionen
        public function RequestAction($Ident, $Value)
        {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }

        //Mülldaten aktualisieren
        public function UpdateWasteTimes()
        {
            $this->SetStatus(102);
            $ModulInfo = IPS_GetInstance($this->InstanceID);
            $ModulName = $ModulInfo['ModuleInfo']['ModuleName'];

            $HourUpdateTimer = $this->ReadPropertyInteger("IntervalUpdateTimer");
            $HourNotificationTimer = $this->ReadPropertyInteger("IntervalNotificationTimer");
            $MinuteUpdateTimer = $this->ReadPropertyInteger("IntervalUpdateTimerMinute");
            $MinuteNotificationTimer = $this->ReadPropertyInteger("IntervalNotificationTimerMinute");

            $this->SendDebug($ModulName, "Starting updates of waste times." , 0);
            //Settings-Variablen:
            $PushInstanceID = $this->ReadPropertyInteger("PushInstanceID");
            $MailInstanceID = $this->ReadPropertyInteger("MailInstanceID");
            if ($this->ReadPropertyInteger("PushInstanceID") > 0) {
                $PushIsActive = true;
            }
            else {
                $PushIsActive = false;
            }
            if ($this->ReadPropertyInteger("MailInstanceID") > 0) {
                $MailIsActive = true;
            }
            else {
                $MailIsActive = false;
            }
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
                $ActualHour = date('H');
                If ($HourNotificationTimer <> (int)$ActualHour)
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
                $this->SendDebug($ModulName, "One or more of the waste time strings are empty!", 0);
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
                $nextTermine['Restmüll'] = closest($arrHM, new DateTime('today midnight'));
            }
            If ($this->ReadPropertyBoolean("cbxPP")) {
                $arrPP = explode("\n", $strPP);
                $nextTermine['Wertstoff'] = closest($arrPP, new DateTime('today midnight'));
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

            sleep(1);
            $this->SendDebug($ModulName, "Next update time: ".$HourUpdateTimer.":".$MinuteUpdateTimer, 0);
            $this->SendDebug($ModulName, "Next notification time: ".$HourNotificationTimer.":".$MinuteNotificationTimer, 0);
            $this->SetNewTimerInterval($HourUpdateTimer.":".$MinuteUpdateTimer.":17", "UpdateTimer");
            $this->SetNewTimerInterval($HourNotificationTimer.":".$MinuteNotificationTimer.":07", "NotificationTimer");
        }

        //Demodaten setzen
        public function SetDemoData()
        {
            If ($this->ReadPropertyBoolean("cbxGS")) {
                $varGSID = IPS_GetObjectIDByIdent("YellowBagTimes", $this->InstanceID);
                SetValueString($varGSID,
                "04.01.2018\n17.01.2018\n31.01.2018\n14.02.2018\n28.02.2018\n14.03.2018\n28.03.2018\n11.04.2018\n25.04.2018\n09.05.2018\n24.05.2018\n06.06.2018\n20.06.2018\n04.07.2018\n18.07.2018\n01.08.2018\n15.08.2018\n29.08.2018\n12.09.2018\n26.09.2018\n10.10.2018\n24.10.2018\n07.11.2018\n21.11.2018\n05.12.2018\n19.12.2018");
            }
            If ($this->ReadPropertyBoolean("cbxHM")) {
                $varHMID = IPS_GetObjectIDByIdent("WasteTimes", $this->InstanceID);
                $bolVarHM = SetValueString($varHMID,
                "03.01.2018\n16.01.2018\n30.01.2018\n13.02.2018\n27.02.2018\n13.03.2018\n27.03.2018\n10.04.2018\n24.04.2018\n08.05.2018\n23.05.2018\n05.06.2018\n19.06.2018\n03.07.2018\n17.07.2018\n31.07.2018\n14.08.2018\n28.08.2018\n11.09.2018\n25.09.2018\n09.10.2018\n23.10.2018\n06.11.2018\n20.11.2018\n04.12.2018\n18.12.2018");
            }
            If ($this->ReadPropertyBoolean("cbxPP")) {
                $varPPID = IPS_GetObjectIDByIdent("PaperTimes", $this->InstanceID);
                $bolVarPP = SetValueString($varPPID,
                "24.01.2018\n21.02.2018\n21.03.2018\n18.04.2018\n16.05.2018\n13.06.2018\n11.07.2018\n08.08.2018\n05.09.2018\n04.10.2018\n01.11.2018\n28.11.2018\n27.12.2018");
            }
            If ($this->ReadPropertyBoolean("cbxBO")) {
                $varBOID = IPS_GetObjectIDByIdent("BioTimes", $this->InstanceID);
                $bolVarBO = SetValueString($varBOID,
                "25.01.2018\n22.02.2018\n22.03.2018\n19.04.2018\n17.05.2018\n14.06.2018\n11.07.2018\n09.08.2018\n06.09.2018\n04.10.2018\n01.11.2018\n27.11.2018\n27.12.2018");
            }
            echo "Demodaten wurden erfolgreich hinterlegt.";
            $this->SetStatus(102);
        }

        /**
         * Create a timer interval.
         *
         * @access protected
         * @param  string $nextTime String for the next time to set.
         * @param  string $TimerName Name of timer to update.
         */
        protected function SetNewTimerInterval($nextTime, $TimerName)
        {
            $ModulInfo = IPS_GetInstance($this->InstanceID);
            $ModulName = $ModulInfo['ModuleInfo']['ModuleName'];

            $now = time();
            $today = date("Y-m-d");
            $nextTimerInterval = strtotime($today.$nextTime);
            $calTime = $nextTimerInterval - $now;
            If ($calTime > 0)
            {
                $this->SetTimerInterval($TimerName, $calTime * 1000);
                $this->SendDebug($ModulName, "Next milliseconds for Timer " . $TimerName . " is " . $calTime . ".", 0);
            }
            Else
            {
                $calTime = strtotime("+1 day " . $nextTime);
                $this->SetTimerInterval($TimerName, (($calTime - $now) * 1000));
                $this->SendDebug($ModulName, "Next milliseconds for timer " . $TimerName . " is " . (($calTime - $now) * 1000), 0);
            }
        }
    }
?>
