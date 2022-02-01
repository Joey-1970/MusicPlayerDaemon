<?
    // Klassendefinition
    class MusicPlayerDaemon extends IPSModule 
    {
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->RequireParent("{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}");
            	$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("IPAddress", "127.0.0.1");
		$this->RegisterPropertyInteger("Port", 6600);
		
		// Status-Variablen anlegen
		$this->RegisterVariableInteger("LastKeepAlive", "Letztes Keep Alive", "~UnixTimestamp", 10);
		
		$this->RegisterVariableInteger("Volume","Volume","~Intensity.100", 50);
		$this->EnableAction("Volume");

        }
       	
	public function GetConfigurationForm() { 
		$arrayStatus = array(); 
		$arrayStatus[] = array("code" => 101, "icon" => "inactive", "caption" => "Instanz wird erstellt"); 
		$arrayStatus[] = array("code" => 102, "icon" => "active", "caption" => "Instanz ist aktiv");
		$arrayStatus[] = array("code" => 104, "icon" => "inactive", "caption" => "Instanz ist inaktiv");
		$arrayStatus[] = array("code" => 200, "icon" => "error", "caption" => "Instanz ist fehlerhaft"); 
		$arrayStatus[] = array("code" => 202, "icon" => "error", "caption" => "Kommunikationfehler!");
		
		$arrayElements = array(); 
		$arrayElements[] = array("name" => "Open", "type" => "CheckBox",  "caption" => "Aktiv"); 
		$arrayElements[] = array("type" => "ValidationTextBox", "name" => "IPAddress", "caption" => "IP");
		$arrayElements[] = array("type" => "NumberSpinner", "name" => "Port", "caption" => "Port (1 - 65535)", "minimum" => 1, "maximum" => 65535);
				
		$arrayActions = array(); 
		$arrayActions[] = array("type" => "Label", "label" => "Test Center"); 
		$arrayActions[] = array("type" => "TestCenter", "name" => "TestCenter");
		
 		return JSON_encode(array("status" => $arrayStatus, "elements" => $arrayElements, "actions" => $arrayActions)); 		 
 	} 
	
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() 
        {
                // Diese Zeile nicht löschen
                parent::ApplyChanges();
		
		If (IPS_GetKernelRunlevel() == KR_READY) {
			$ParentID = $this->GetParentID();
			If ($ParentID > 0) {
				If (IPS_GetProperty($ParentID, 'Host') <> $this->ReadPropertyString('IPAddress')) {
		                	IPS_SetProperty($ParentID, 'Host', $this->ReadPropertyString('IPAddress'));
				}
				If (IPS_GetProperty($ParentID, 'Port') <> $this->ReadPropertyInteger('Port')) {
		                	IPS_SetProperty($ParentID, 'Port', $this->ReadPropertyInteger('Port'));
				}
				If (IPS_GetProperty($ParentID, 'Open') <> $this->ReadPropertyBoolean("Open")) {
		                	IPS_SetProperty($ParentID, 'Open', $this->ReadPropertyBoolean("Open"));
				}
				
				if(IPS_HasChanges($ParentID))
				{
				    	$Result = @IPS_ApplyChanges($ParentID);
					If ($Result) {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket erfolgreich", 0);
					}
					else {
						$this->SendDebug("ApplyChanges", "Einrichtung des Client Socket nicht erfolgreich!", 0);
					}
				}
			}
			
			If ($this->ReadPropertyBoolean("Open") == true) {
				
				If ($this->ConnectionTest() == true) {
					If ($this->GetStatus() <> 102) {
						$this->SetStatus(102);
					}
					$this->SetNewStation("http://172.27.2.205:9981/stream/channel/800c150e9a6b16078a4a3b3b5aee0672");
					$this->Status();
				}
			}
			else {
				If ($this->GetStatus() <> 104) {
					$this->SetStatus(104);
				}
			}	   
		}
		
		
		
	}
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
			case "Volume":
				$this->SetVolume($Value);
				break;
	      		
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	    
	public function ReceiveData($JSONString) 
	{
		// Empfangene Daten vom I/O
	    	$Data = json_decode($JSONString);
		$Message = trim($Message, "\x00..\x1F");
			$this->SendDebug("ReceiveData", $Message, 0);
			
			switch($Message) {
				case preg_match('/OK MPD.*/', $Message) ? $Message : !$Message:
					$this->SetValue("LastKeepAlive", time() );
					break;
				case "OK":
					$this->SendDebug("ReceiveData", "OK: Befehl erfolgreich", 0);
					break;
				
			}
		
		
		
	}
	    
	// Beginn der Funktionen
	public function Status()
	{
		$this->Send("status\n");
	}
	    
	public function Play() 
	{
		$this->SendCommand("play\n");
	}

	public function Pause(int $State) {
		$this->SendCommand("pause ".$State."\n");
	}

	public function Stop() {
		$this->SendCommand("stop\n");
	}

	public function Previous() {
		$this->SendCommand("previous\n");
	}

	public function Next() {
		$this->SendCommand("next\n");
	}

	public function SetNewStation(String $StationURL) 
	{
		$this->SendCommand("clear\n");
		$this->SendCommand("add ".$StationURL." \n");
		usleep(50000);
	}

	public function SetVolume(int $Volume) {
		$this->SendCommand("setvol ".$Volume."\n");
	}
	    
	    
	public function SendCommand(string $Command)
	{
		If (($this->HasActiveParent()) AND ($this->ReadPropertyBoolean("Open") == true)) {
			$this->SendDataToParent(json_encode(Array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => $Command)));
		}
	}
	    
	private function ConnectionTest()
	{
	      	$result = false;
		$IPAddress = $this->ReadPropertyString("IPAddress");
		$Port = $this->ReadPropertyInteger("Port");
	      	If (Sys_Ping($IPAddress, 300)) {
			$status = @fsockopen($IPAddress, $Port, $errno, $errstr, 10);
			if (!$status) {
				$this->SendDebug("ConnectionTest", "Port ".$Port." ist geschlossen!", 0);
				IPS_LogMessage("MusicPlayerDaemon","Port ".$Port." ist geschlossen!");
				If ($this->GetStatus() <> 202) {
					$this->SetStatus(202);
				}
			}
		      	else {
				$result = true;
				If ($this->GetStatus() <> 102) {
					$this->SetStatus(102);
				}
			}
		}
		else {
			$this->SendDebug("ConnectionTest", "IP ".$IPAddress." reagiert nicht!", 0);
			IPS_LogMessage("MusicPlayerDaemon","IP ".$IPAddress." reagiert nicht!");
			If ($this->GetStatus() <> 202) {
				$this->SetStatus(202);
			}
		}
	return $result;
	}
	
	private function GetParentID()
	{
		$ParentID = (IPS_GetInstance($this->InstanceID)['ConnectionID']);  
	return $ParentID;
	}

	private function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 1);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 1)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);        
	}
	    
	private function RegisterProfileFloat($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits)
	{
	        if (!IPS_VariableProfileExists($Name))
	        {
	            IPS_CreateVariableProfile($Name, 2);
	        }
	        else
	        {
	            $profile = IPS_GetVariableProfile($Name);
	            if ($profile['ProfileType'] != 2)
	                throw new Exception("Variable profile type does not match for profile " . $Name);
	        }
	        IPS_SetVariableProfileIcon($Name, $Icon);
	        IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
	        IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	        IPS_SetVariableProfileDigits($Name, $Digits);
	}
}
?>
