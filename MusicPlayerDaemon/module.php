<?
    // Klassendefinition
    class ShairportSync extends IPSModule 
    {
	// https://github.com/mikebrady/shairport-sync/blob/master/MQTT.md
	    
	// Überschreibt die interne IPS_Create($id) Funktion
        public function Create() 
        {
            	// Diese Zeile nicht löschen.
            	parent::Create();
		$this->ConnectParent("{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}");
            	$this->RegisterPropertyBoolean("Open", false);
		$this->RegisterPropertyString("Topic", "Topic");
		
		// Profile anlegen
		$this->RegisterMediaObject("Cover_".$this->InstanceID, "Cover_".$this->InstanceID, 1, $this->InstanceID, 200, true, "Cover.png");
		
		$this->RegisterProfileInteger("ShairportSync.Remote", "Remote", "", "", 0, 3, 0);
		IPS_SetVariableProfileAssociation("ShairportSync.Remote", 0, "I<", "Remote", -1);
		IPS_SetVariableProfileAssociation("ShairportSync.Remote", 1, ">/II", "Remote", -1);
		IPS_SetVariableProfileAssociation("ShairportSync.Remote", 2, ">I", "Remote", -1);
		
		$this->RegisterProfileInteger("ShairportSync.Volume", "Intensity", "", "", 0, 3, 0);
		IPS_SetVariableProfileAssociation("ShairportSync.Volume", 0, "-", "Intensity", -1);
		IPS_SetVariableProfileAssociation("ShairportSync.Volume", 1, "Mute", "Intensity", -1);
		IPS_SetVariableProfileAssociation("ShairportSync.Volume", 2, "+", "Intensity", -1);
			
		$this->RegisterProfileFloat("ShairportSync.VolumeIntensity", "Intensity", "", " dB", -30, 0, 0.1, 2);
		
		// Status-Variablen anlegen
		$this->RegisterVariableBoolean("ActiveConnecion", "Aktive Verbindung", "~Switch", 10);
		$this->RegisterVariableString("Artist", "Interpret", "", 10);	
		$this->RegisterVariableString("Album", "Album", "", 20);
		$this->RegisterVariableString("Title", "Titel", "", 30);
		$this->RegisterVariableString("Genre", "Genre", "", 40);
		
		$this->RegisterVariableString("Songalbum", "Songalbum", "", 50);
		
		$this->RegisterVariableInteger("Remote", "Remote", "ShairportSync.Remote", 80);
		$this->EnableAction("Remote");
		
		$this->RegisterVariableInteger("Volume", "Volume", "ShairportSync.Volume", 90);
		$this->EnableAction("Volume");
		
		$this->RegisterVariableFloat("VolumeIntensity", "Volume", "ShairportSync.VolumeIntensity", 100);
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
		$arrayElements[] = array("name" => "Topic", "type" => "ValidationTextBox",  "caption" => "Topic"); 
		
		
		$arrayElements[] = array("type" => "Label", "caption" => "_____________________________________________________________________________________________________");
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
		
		$Content = file_get_contents(__DIR__ . '/../imgs/AirPlay.png'); 
		IPS_SetMediaContent($this->GetIDForIdent("Cover_".$this->InstanceID), base64_encode($Content));  //Bild Base64 codieren und ablegen
		IPS_SendMediaEvent($this->GetIDForIdent("Cover_".$this->InstanceID)); //aktualisieren

		$this->SetValue("ActiveConnecion", false);
		
		If ($this->ReadPropertyBoolean("Open") == true) {
			If ($this->GetStatus() <> 102) {
				$this->SetStatus(102);
			}
			
		}
		else {
			If ($this->GetStatus() <> 104) {
				$this->SetStatus(104);
			}
			
		}
		
	}
	
	public function RequestAction($Ident, $Value) 
	{
  		switch($Ident) {
	      		case "Remote":
			    	If ($this->ReadPropertyBoolean("Open") == true) {
					$Commands = array("nextitem", "playpause", "previtem");
					$this->SendCommand($Commands[$Value]);
					$this->SetValue($Ident, 1);
			    	}
	            		break;
			case "Volume":
			    	If ($this->ReadPropertyBoolean("Open") == true) {
					$Commands = array("volumedown", "mutetoggle", "volumeup");
					$this->SendCommand($Commands[$Value]);
					$this->SetValue($Ident, 1);
			    	}
	            		break;
	        default:
	            throw new Exception("Invalid Ident");
	    	}
	}
	    
	public function ReceiveData($JSONString) 
	{
		// Empfangene Daten vom I/O
	    	$Data = json_decode($JSONString);
		if (isset($Data->PacketType)) {
	    		$PacketType = utf8_decode($Data->PacketType);
		} else {
			return;
		}
		$QualityOfService = utf8_decode($Data->QualityOfService);
		$Retain = utf8_decode($Data->Retain);
		$Topic = utf8_decode($Data->Topic);
		$Payload = utf8_decode($Data->Payload);
		
		//$this->SendDebug("ReceiveData", "PacketType: ".$PacketType." QualityOfService: ".$QualityOfService." Retain: ".$Retain." Topic: ".$Topic." Payload: ".$Payload, 0);
		
		$this->ShowMQTTData($PacketType, $QualityOfService, $Retain, $Topic, $Payload);
		
	}
	    
	// Beginn der Funktionen
	private function ShowMQTTData(int $PacketType, int $QualityOfService, Bool $Retain, String $Topic, String $Payload)
	{
		$MainTopic = $this->ReadPropertyString("Topic");
		If ($this->ReadPropertyBoolean("Open") == true) {
			switch($Topic) {
				case $MainTopic."/active_end": // Ende der aktiven Verbindung
					If ($this->GetValue("ActiveConnecion") == true) {
						$this->SetValue("ActiveConnecion", false);
						$this->SetValue("Artist", "-");
						$this->SetValue("Album", "-");
						$this->SetValue("Title", "-");
						$this->SetValue("Genre", "-");
						$this->SetValue("Songalbum", "-");
						$this->SetValue("VolumeIntensity", 0);
						$Content = file_get_contents(__DIR__ . '/../imgs/AirPlay.png'); 
						IPS_SetMediaContent($this->GetIDForIdent("Cover_".$this->InstanceID), base64_encode($Content));  //Bild Base64 codieren und ablegen
						IPS_SendMediaEvent($this->GetIDForIdent("Cover_".$this->InstanceID)); //aktualisieren
					}
					break;
				case $MainTopic."/active_start": // Beginn der aktiven Verbindung
					If ($this->GetValue("ActiveConnecion") == false) {
						$this->SetValue("ActiveConnecion", true);
					}
					break;
				case $MainTopic."/core/asar": // Artist
					If ($this->GetValue("Artist") <> $Payload) {
						$this->SetValue("Artist", $Payload);
					}
					break;
				case $MainTopic."/core/asal": // Album
					If ($this->GetValue("Album") <> $Payload) {
						$this->SetValue("Album", $Payload);
					}
					break;
				case $MainTopic."/core/minm": // Titel
					If ($this->GetValue("Title") <> $Payload) {
						$this->SetValue("Title", $Payload);
					}
					break;
				case $MainTopic."/core/asgn": // Genre
					If ($this->GetValue("Genre") <> $Payload) {
						$this->SetValue("Genre", $Payload);
					}
					break;
				case $MainTopic."/core/asfm": // Format
					
					break;
				case $MainTopic."/core/clip": //Client IP
					
					break;
				case $MainTopic."/core/asal": // Songalbum
					If ($this->GetValue("Songalbum") <> $Payload) {
						$this->SetValue("Songalbum", $Payload);
					}
					break;	
					
					
				case $MainTopic."/cover": // Cover
					$this->SendDebug("ShowMQTTData", "Länge Payload: ".strlen($Payload), 0);
					$ImageData = array();
					$ImageData = @getimagesize('data://text/plain;base64' . base64_encode($Payload));
					If (is_array($ImageData) == true) {
						$this->SendDebug("ShowMQTTData", "Coverformat: ".$ImageData['mime'], 0);
						IPS_SetMediaContent($this->GetIDForIdent("Cover_".$this->InstanceID), base64_encode($Payload));  //Bild Base64 codieren und ablegen
						IPS_SendMediaEvent($this->GetIDForIdent("Cover_".$this->InstanceID)); //aktualisieren
					} else {
						$this->SendDebug("ShowMQTTData", "Coverformat: keine Daten erhalten", 0);
					}
					
					break;
				case $MainTopic."/ssnc/pbeg": // Play Stream Begin
					$this->SendDebug("ShowMQTTData", "Play Stream Begin", 0);
					break;
				case $MainTopic."/ssnc/pend": // Play Stream End
					$this->SendDebug("ShowMQTTData", "Play Stream End", 0);
					break;
				case $MainTopic."/ssnc/pfls": // Play Stream Flush
					$this->SendDebug("ShowMQTTData", "Play Stream Flush", 0);
					break;
				case $MainTopic."/ssnc/prsm": // Play Stream Resume
					$this->SendDebug("ShowMQTTData", "Play Stream Resume", 0);
					break;
				case $MainTopic."/ssnc/prgr": // Progress
					$Parts = explode("/", $Payload);
					If (count($Parts) == 3) {
						$StartCurrentPlaySequence = $Parts[0];
						$CurrentPlayPoint = $Parts[1];
						$EndCurrentPlaySequence = $Parts[2];
						$this->SendDebug("ShowMQTTData", "Progess: ".$StartCurrentPlaySequence.":".$CurrentPlayPoint.":".$EndCurrentPlaySequence, 0);
						
					} else {
						$this->SendDebug("ShowMQTTData", "Progess Fehler: ".count($Parts) , 0);
					}
					break;
				case $MainTopic."/ssnc/pvol": // Volume
					$Parts = explode(",", $Payload);
					If (count($Parts) == 4) {
						$AirplayVolume = floatval($Parts[0]);
						$Volume = floatval($Parts[1]);
						$LowestVolume = floatval($Parts[2]);
						$HighestVolume = floatval($Parts[3]);
						$this->SendDebug("ShowMQTTData", "Volume: ".$AirplayVolume.":".$Volume.":".$LowestVolume.":".$HighestVolume, 0);
						If ($this->GetValue("VolumeIntensity") <> $AirplayVolume) {
							$this->SetValue("VolumeIntensity", $AirplayVolume);
						}
						
					} else {
						$this->SendDebug("ShowMQTTData", "Volume Fehler: ".count($Parts) , 0);
					}
					break;
			}
		}
	}
	 
	
	/*
	
p
pvol -- play volume. The volume is sent as a string -- "airplay_volume,volume,lowest_volume,highest_volume", where "volume", "lowest_volume" and "highest_volume" are given in dB. The "airplay_volume" is what's sent by the source (e.g. iTunes) to the player, and is from 0.00 down to -30.00, with -144.00 meaning "mute". This is linear on the volume control slider of iTunes or iOS AirPlay. If the volume setting is being ignored by Shairport Sync itself, the volume, lowest_volume and highest_volume values are zero.
prgr -- progress -- this is metadata from AirPlay consisting of RTP timestamps for the start of the current play sequence, the current play point and the end of the play sequence.
mdst -- a sequence of metadata is about to start. The RTP timestamp associated with the metadata sequence is included as data, if available.
mden -- a sequence of metadata has ended. The RTP timestamp associated with the metadata sequence is included as data, if available.
pcst -- a picture is about to be sent. The RTP timestamp associated with it is included as data, if available.
pcen -- a picture has been sent. The RTP timestamp associated with it is included as data, if available.
snam -- a device e.g. "Joe's iPhone" has started a play session. Specifically, it's the "X-Apple-Client-Name" string.
snua -- a "user agent" e.g. "iTunes/12..." has started a play session. Specifically, it's the "User-Agent" string.
stal -- this is an error message meaning that reception of a large piece of metadata, usually a large picture, has stalled; bad things may happen.
char *commands[] = {"command",    "beginff",       "beginrew",   "mutetoggle", "nextitem",
                      "previtem",   "pause",         "playpause",  "play",       "stop",
                      "playresume", "shuffle_songs", "volumedown", "volumeup",   NULL};
		      
		      
		      
	 */   
	    
	public function SendCommand(String $Command)
	{
		$MainTopic = $this->ReadPropertyString("Topic");
		If ($this->ReadPropertyBoolean("Open") == true) {
			$Response = $this->SendDataToParent(json_encode(Array("DataID"=> "{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}", "PacketType" => 3, "QualityOfService" => 0, "Retain" => false, "Topic" => $MainTopic."/remote", "Payload" => $Command )));
				
		}
		

			
	}
	private function RegisterMediaObject($Name, $Ident, $Typ, $Parent, $Position, $Cached, $Filename)
	{
		$MediaID = @$this->GetIDForIdent($Ident);
		if($MediaID === false) {
		    	$MediaID = 0;
		}
		
		if ($MediaID == 0) {
			 // Image im MedienPool anlegen
			$MediaID = IPS_CreateMedia($Typ); 
			// Medienobjekt einsortieren unter Kategorie $catid
			IPS_SetParent($MediaID, $Parent);
			IPS_SetIdent($MediaID, $Ident);
			IPS_SetName($MediaID, $Name);
			IPS_SetPosition($MediaID, $Position);
                    	IPS_SetMediaCached($MediaID, $Cached);
			$ImageFile = IPS_GetKernelDir()."media".DIRECTORY_SEPARATOR.$Filename;  // Image-Datei
			IPS_SetMediaFile($MediaID, $ImageFile, false);    // Image im MedienPool mit Image-Datei verbinden
		}  
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
