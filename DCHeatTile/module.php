<?php

declare(strict_types=1);

class HeizungskachelHTML extends IPSModule
{
    // Die Optionen für den Auswahl-Schalter (Dropdown)
    private $hkModes = [
        0 => "Aus",
        1 => "Auto",
        2 => "Absenken",
        3 => "Heizen",
        4 => "1xHeizen",
        5 => "Kühlen"
    ];

    // Die neuen 11 tatsächlichen Betriebszustände (Anzeige Übersicht)
    private $opModes = [
        0 => "Aus",
        1 => "Heizen",
        2 => "Rampe Absenken",
        3 => "Absenken",
        4 => "Tag/Nacht Abschaltung",
        5 => "Sommerabschaltung",
        6 => "Frostschutz",
        7 => "Estrich",
        8 => "Handbetrieb",
        9 => "Restwärme",
        10 => "Solarheizen"
    ];

    private $hkIcons = [
        0 => '<svg viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2"><path d="M12 2v10m-7.07-2.93a10 10 0 1014.14 0" stroke-linecap="round"/></svg>', 
        1 => '<svg viewBox="0 0 24 24" fill="none" stroke="#3498db" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2" stroke-linecap="round"/></svg>', 
        2 => '<svg viewBox="0 0 24 24" fill="none" stroke="#9b59b6" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" stroke-linejoin="round"/></svg>', 
        3 => '<svg viewBox="0 0 24 24" fill="none" stroke="#f39c12" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" stroke-linecap="round"/></svg>', 
        4 => '<svg viewBox="0 0 24 24" fill="none" stroke="#e67e22" stroke-width="2"><path d="M12 2c0 0-5 6-5 11a5 5 0 0010 0c0-5-5-11-5-11z" stroke-linejoin="round"/></svg>', 
        5 => '<svg viewBox="0 0 24 24" fill="none" stroke="#00cec9" stroke-width="2"><path d="M17.5 19.5L12 12m0 0L6.5 4.5M12 12l5.5-7.5M12 12L6.5 19.5M4 12h16M12 4v16" stroke-linecap="round" stroke-linejoin="round"/></svg>' 
    ];

    public function Create()
    {
        parent::Create();
        
        $this->RegisterPropertyInteger("OutsideTemp", 0);
        $this->RegisterPropertyInteger("AvgOutsideTemp", 0);

        $this->RegisterPropertyInteger("SourceFill", 0);       
        $this->RegisterPropertyInteger("SourceBoiler", 0);     
        $this->RegisterPropertyInteger("SourcePuffer3", 0);    
        $this->RegisterPropertyInteger("SourcePuffer2", 0);    
        $this->RegisterPropertyInteger("SourcePuffer1", 0);    
        $this->RegisterPropertyInteger("Boiler_State", 0);     
        $this->RegisterPropertyInteger("Boiler_Temp", 0);      

        for($i=1; $i<=6; $i++) {
            $this->RegisterPropertyString("C{$i}_Name", "HK $i");
            $this->RegisterPropertyInteger("C{$i}_State", 0);
            $this->RegisterPropertyInteger("C{$i}_TargetTemp", 0); 
            $this->RegisterPropertyInteger("C{$i}_Temp", 0);       
            $this->RegisterPropertyInteger("C{$i}_Mode", 0);
            $this->RegisterPropertyInteger("C{$i}_OpMode", 0); // NEU: Ist-Status
        }

        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) $this->UnregisterMessage($senderID, VM_UPDATE);
            }
        }

        $vars = [
            "OutsideTemp", "AvgOutsideTemp", 
            "SourceFill", "SourceBoiler", "SourcePuffer3", "SourcePuffer2", "SourcePuffer1",
            "Boiler_State", "Boiler_Temp"
        ];

        for($i=1; $i<=6; $i++) {
            $vars[] = "C{$i}_State";
            $vars[] = "C{$i}_TargetTemp"; 
            $vars[] = "C{$i}_Temp";
            $vars[] = "C{$i}_Mode";
            $vars[] = "C{$i}_OpMode"; // NEU
        }

        foreach ($vars as $prop) {
            $id = $this->ReadPropertyInteger($prop);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message == VM_UPDATE) {
            $this->UpdateVisualizationValue($this->GetAllValuesAsJSON());
        }
    }

    private function GetAllValuesAsJSON()
    {
        $getVal = function($propName) {
            $id = $this->ReadPropertyInteger($propName);
            if ($id > 0 && IPS_VariableExists($id)) return GetValue($id);
            return 0; 
        };

        $data = [
            't_out'     => $getVal("OutsideTemp"),
            't_out_avg' => $getVal("AvgOutsideTemp"),
            'fill'    => $getVal("SourceFill"),
            't_boil'  => $getVal("SourceBoiler"),
            't_p3'    => $getVal("SourcePuffer3"), 
            't_p2'    => $getVal("SourcePuffer2"),
            't_p1'    => $getVal("SourcePuffer1"),
            'ov_boil_state' => $getVal("Boiler_State"), 
            'ov_boil_temp'  => $getVal("Boiler_Temp"),
        ];

        $circuits = [];
        for($i=1; $i<=6; $i++) {
            $idState = $this->ReadPropertyInteger("C{$i}_State");
            if($idState > 0 && IPS_VariableExists($idState)) {
                $idMode = $this->ReadPropertyInteger("C{$i}_Mode");
                $idOpMode = $this->ReadPropertyInteger("C{$i}_OpMode"); // NEU
                $circuits[] = [
                    'id' => $i,
                    'state' => GetValue($idState),
                    'target_temp' => $getVal("C{$i}_TargetTemp"),
                    'temp' => $getVal("C{$i}_Temp"),              
                    'mode' => ($idMode > 0 && IPS_VariableExists($idMode)) ? GetValue($idMode) : -1,
                    'op_mode' => ($idOpMode > 0 && IPS_VariableExists($idOpMode)) ? GetValue($idOpMode) : -1 // NEU
                ];
            }
        }
        $data['circuits'] = $circuits;

        return json_encode($data);
    }

    public function GetVisualizationTile()
    {
        $initialData = $this->GetAllValuesAsJSON();

        // -----------------------------------------------------------
        // 1. DYNAMISCHE HEIZKREIS GENERIERUNG (SVG)
        // -----------------------------------------------------------
        $hkSVG = "";
        $configuredCircuits = [];
        for($i=1; $i<=6; $i++) {
            if($this->ReadPropertyInteger("C{$i}_State") > 0) {
                $configuredCircuits[] = $i;
            }
        }
        $count = count($configuredCircuits);
        
        $startY = 100; 
        $blockHeight = ($count > 4) ? 70 : 90; 
        $gap = 10;

        if ($count > 0) {
            $hkSVG .= '<path d="M 470 200 L 580 200" stroke="#e74c3c" stroke-width="8" fill="none" />';
            $hkSVG .= '<path d="M 470 300 L 560 300" stroke="#3498db" stroke-width="8" fill="none" />';

            $yTopRed  = $startY + ($blockHeight/2) - 10;
            $yTopBlue = $startY + ($blockHeight/2) + 20;

            $yBottomIndex = $count - 1;
            $yBottomBase  = $startY + ($yBottomIndex * ($blockHeight + $gap));
            $yBotRed      = $yBottomBase + ($blockHeight/2) - 10;
            $yBotBlue     = $yBottomBase + ($blockHeight/2) + 20;

            $hkSVG .= '<path d="M 580 '.$yTopRed.' L 580 '.$yBotRed.'" stroke="#e74c3c" stroke-width="8" fill="none" />';
            $hkSVG .= '<path d="M 560 '.$yTopBlue.' L 560 '.$yBotBlue.'" stroke="#3498db" stroke-width="8" fill="none" />';
        }

        foreach($configuredCircuits as $index => $cIndex) {
            $yPos = $startY + ($index * ($blockHeight + $gap));
            $name = $this->ReadPropertyString("C{$cIndex}_Name");
            
            $hkSVG .= '
            <g class="clickable" onclick="openModal(\'modal_circuit_'.$cIndex.'\')" transform="translate(600, '.$yPos.')">
                <rect x="0" y="0" width="190" height="'.$blockHeight.'" rx="5" fill="#34495e" stroke="#2c3e50" stroke-width="2"/>
                
                <line x1="-20" y1="'.($blockHeight/2 - 10).'" x2="0" y2="'.($blockHeight/2 - 10).'" stroke="#e74c3c" stroke-width="4" />
                <line x1="-40" y1="'.($blockHeight/2 + 20).'" x2="0" y2="'.($blockHeight/2 + 20).'" stroke="#3498db" stroke-width="4" />

                <text x="10" y="16" style="fill: #e67e22; font-family: Arial; font-weight: bold; font-size: 14px;">'.$name.'</text>
                
                <text id="main_mode_text_'.$cIndex.'" x="10" y="32" style="fill: #e67e22; font-family: Arial; font-size: 10px; opacity: 0.8;">Betriebsmodus: --</text>
                
                <text id="main_opmode_text_'.$cIndex.'" x="10" y="44" style="fill: #e67e22; font-family: Arial; font-size: 10px; opacity: 0.8;">HK Heizmodus: --</text>
                
                <g transform="translate(150, '.($blockHeight/2).')">
                    <circle cx="0" cy="0" r="18" stroke="white" stroke-width="2" fill="none"/>
                    <path id="pump_icon_'.$cIndex.'" d="M 0 0 L 12 -8 L 12 8 Z" fill="white" transform-origin="0 0" />
                </g>

                <text x="10" y="'.($blockHeight - 8).'" style="fill: #e67e22; font-family: Arial; font-weight: bold; font-size: 18px;">
                    <tspan id="val_target_temp_'.$cIndex.'">--</tspan> °C
                </text>
            </g>';
        }

        // -----------------------------------------------------------
        // 2. MODALS GENERIEREN (KOMPAKTES DROP-UP MENÜ)
        // -----------------------------------------------------------
        $modalsHTML = "";
        foreach($configuredCircuits as $cIndex) {
            $name = $this->ReadPropertyString("C{$cIndex}_Name");
            $modeVarId = $this->ReadPropertyInteger("C{$cIndex}_Mode");
            
            $dropdownItemsHTML = "";
            if ($modeVarId > 0) {
                foreach($this->hkModes as $val => $label) {
                    $icon = $this->hkIcons[$val];
                    $dropdownItemsHTML .= '
                    <div class="dropdown-item" onclick="requestAction('.$modeVarId.', '.$val.')">
                        <span class="mode-icon">'.$icon.'</span> '.$label.'
                    </div>';
                }
            } else {
                $dropdownItemsHTML = '<div class="dropdown-item" style="color: #7f8c8d; grid-column: span 2; text-align: center;">Keine Variable verknüpft</div>';
            }

            $modalsHTML .= '
            <div id="modal_circuit_'.$cIndex.'" class="modal-overlay">
                <div class="modal-content" style="max-width: 400px; max-height: 480px;">
                    <div class="close-btn" onclick="closeModal(\'modal_circuit_'.$cIndex.'\')">&times;</div>
                    <div class="modal-body" style="text-align:center; padding-top:25px; display: flex; flex-direction: column; justify-content: space-between; overflow: visible;">
                        
                        <div>
                            <h2 style="color:#2c3e50; margin-bottom: 5px;">'.$name.'</h2>
                            
                            <div id="detail_state_'.$cIndex.'" style="font-size: 16px; font-weight: bold; margin-bottom: 15px;">Status laden...</div>
                            
                            <div style="font-size: 36px; margin: 10px 0; color:#e67e22; font-weight: bold;">
                                <span id="detail_target_temp_'.$cIndex.'">--</span> °C
                                <div style="font-size: 14px; font-weight: normal; color: #7f8c8d; margin-top: -5px;">Raum-Soll</div>
                            </div>

                            <div style="font-size: 24px; margin: 15px 0; color:#3498db; font-weight: bold;">
                                <span id="detail_flow_temp_'.$cIndex.'">--</span> °C
                                <div style="font-size: 12px; font-weight: normal; color: #7f8c8d; margin-top: -2px;">Vorlauf</div>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <div style="font-size: 14px; font-weight: bold; color: #34495e; margin-bottom: 5px;">Betriebsmodus</div>
                            <div class="custom-dropdown">
                                <div class="dropdown-menu">
                                    '.$dropdownItemsHTML.'
                                </div>
                                <div class="dropdown-trigger">
                                    <span id="current_icon_'.$cIndex.'" class="mode-icon">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="#bdc3c7" stroke-width="2" fill="none"/></svg>
                                    </span>
                                    <span id="current_text_'.$cIndex.'">Laden...</span>
                                    <span class="dropdown-arrow">▲</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>';
        }

        // -----------------------------------------------------------
        // 3. HAUPT SVG
        // -----------------------------------------------------------
        $mainOverview = '
        <svg viewBox="0 0 800 500" style="width:100%; height:100%;">
            <defs>
                <linearGradient id="mainBlue" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3498db"/><stop offset="100%" stop-color="#2980b9"/></linearGradient>
                <linearGradient id="mainRedFade" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#e74c3c" stop-opacity="1"/><stop offset="80%" stop-color="#e74c3c" stop-opacity="1"/><stop offset="100%" stop-color="#e74c3c" stop-opacity="0"/></linearGradient>
                <linearGradient id="boilerHalfGradient" x1="0" y1="0" x2="0" y2="1"><stop offset="50%" stop-color="#95a5a6"/><stop offset="50%" stop-color="#d35400"/></linearGradient>
                <clipPath id="tankClipRound"><rect x="350" y="100" width="120" height="300" rx="10" /></clipPath>
                <filter id="maskBlur" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur in="SourceGraphic" stdDeviation="15" result="blur" /></filter>
                <mask id="hotWaterMask" maskUnits="userSpaceOnUse" filter="url(#maskBlur)">
                    <rect x="0" y="0" width="800" height="500" fill="black" />
                    <g style="transform: translateY(calc(100px + (var(--fill-val) * 3px))); transition: transform 1s ease-in-out;">
                        <g class="wave-anim-mask"><path d="M -400 0 Q -370 5 -340 0 T -280 0 T -220 0 T -160 0 T -100 0 T -40 0 T 20 0 T 80 0 T 140 0 T 200 0 T 260 0 T 320 0 T 380 0 T 440 0 T 500 0 T 560 0 T 620 0 T 680 0 T 740 0 T 800 0 V -400 H -400 Z" fill="white" /></g>
                    </g>
                </mask>
            </defs>

            <g transform="translate(410, 40)">
                <rect x="-140" y="-18" width="280" height="34" rx="17" fill="#34495e" stroke="#2c3e50" stroke-width="2" />
                <text x="0" y="4" text-anchor="middle" style="fill: #ecf0f1; font-family: Arial; font-weight: bold; font-size: 14px;">
                    Außen: <tspan id="main_out_temp" style="fill: #3498db;">--</tspan> °C  |  Ø <tspan id="main_out_avg_temp" style="fill: #3498db;">--</tspan> °C
                </text>
            </g>

            <path d="M 220 250 L 350 250" stroke="#555" stroke-width="10" /> 
            <path d="M 470 200 L 480 200" stroke="#e74c3c" stroke-width="8" /> 
            <path d="M 470 300 L 480 300" stroke="#3498db" stroke-width="8" /> 

            '.$hkSVG.'

            <g class="clickable" onclick="openModal(\'modal_boiler\')">
                <rect id="main_boiler_rect" x="50" y="150" width="170" height="200" rx="5" fill="#95a5a6" stroke="#7f8c8d" stroke-width="3" style="transition: fill 0.5s;"/>
                <text x="135" y="180" text-anchor="middle" style="fill: white; font-weight: bold; font-size: 18px;">OFEN</text>
                <text id="boiler_status_text" x="135" y="200" text-anchor="middle" style="fill: white; font-size: 12px; font-style: italic;">Aus</text>
                <path d="M 135 220 Q 155 260 135 300 Q 115 260 135 220" fill="#f9ca24" id="flame_icon" style="opacity: 0; transition: opacity 0.5s, fill 0.5s;"/>
                <g id="warning_icon" style="opacity: 0; transition: opacity 0.5s;" transform="translate(135, 250)">
                    <path d="M 0 -30 L -25 20 L 25 20 Z" fill="#f9ca24" stroke="#e67e22" stroke-width="2" stroke-linejoin="round"/>
                    <text x="0" y="10" text-anchor="middle" fill="#e74c3c" font-weight="bold" font-size="24">!</text>
                </g>
                <g id="alert_icon" style="opacity: 0; transition: opacity 0.5s;" transform="translate(135, 250)">
                    <text x="0" y="15" text-anchor="middle" fill="#f9ca24" font-weight="bold" font-size="50" style="text-shadow: 1px 1px 2px #a04000;">!</text>
                </g>
                <text x="135" y="330" text-anchor="middle" style="fill: white; font-size: 16px;"><tspan id="main_boil_temp">--</tspan> °C</text>
            </g>

            <g class="clickable" onclick="openModal(\'modal_buffer\')">
                <g clip-path="url(#tankClipRound)">
                    <rect x="350" y="100" width="120" height="300" fill="url(#mainBlue)" />
                    <rect x="350" y="100" width="120" height="10" fill="url(#mainRedFade)" mask="url(#hotWaterMask)" style="height: calc(var(--fill-val) * 3px); transition: height 1s ease-in-out;" />
                </g> 
                <rect x="350" y="100" width="120" height="300" rx="10" fill="none" stroke="#7f8c8d" stroke-width="3"/>
                <text x="410" y="250" text-anchor="middle" style="fill: white; font-weight: bold; font-size: 18px; text-shadow: 1px 1px 2px #333;">PUFFER</text>
                <text x="410" y="280" text-anchor="middle" style="fill: white; font-size: 14px; text-shadow: 1px 1px 2px #333;"><tspan id="main_buf_fill">--</tspan> %</text>
            </g>
        </svg>';

        $popupBufferContent = $this->getBufferPopupSVG(); 

        $modeMapJSON = json_encode($this->hkModes);
        $iconMapJSON = json_encode($this->hkIcons);
        $opModeMapJSON = json_encode($this->opModes); // NEU

        $html = <<<HTML
        <style>
            :root { --fill-val: 0; } 
            .visu-container { position: relative; width: 100%; height: 100%; font-family: sans-serif; overflow: hidden; background: #ecf0f1; }
            .clickable { cursor: pointer; transition: opacity 0.2s; }
            .clickable:hover { opacity: 0.8; filter: brightness(1.1); }
            
            .modal-overlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
            .modal-content { background: white; width: 90%; height: 95%; border-radius: 10px; position: relative; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; flex-direction: column; }
            .close-btn { position: absolute; top: 5px; right: 10px; font-size: 30px; font-weight: bold; color: #e74c3c; cursor: pointer; z-index: 200; }
            .modal-body { flex: 1; padding: 5px; overflow: visible; }
            
            @keyframes spin { 100% { transform: rotate(360deg); } }
            .pump-active { animation: spin 1s linear infinite; fill: #2ecc71 !important; }
            .pump-inactive { fill: white !important; }
            .flame-active { opacity: 1 !important; fill: #f9ca24 !important; filter: drop-shadow(0 0 5px #f9ca24); }
            @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.2; } 100% { opacity: 1; } }
            .blink-active { animation: blink 1s infinite; opacity: 1 !important; }
            @keyframes waveSlideMask { from { transform: translateX(0px); } to { transform: translateX(-240px); } }
            .wave-anim-mask { animation: waveSlideMask 6s linear infinite; }

            .custom-dropdown {
                position: relative;
                display: inline-block;
                width: 260px;
                margin: 0 auto;
                text-align: left;
            }
            .dropdown-trigger {
                background: #ecf0f1;
                border: 2px solid #bdc3c7;
                border-radius: 8px;
                padding: 12px 15px;
                color: #2c3e50;
                font-weight: bold;
                font-size: 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: border-color 0.2s;
            }
            .dropdown-trigger:hover { border-color: #95a5a6; }
            .dropdown-arrow { margin-left: auto; font-size: 12px; color: #7f8c8d; }
            
            .dropdown-menu {
                display: grid; 
                grid-template-columns: 1fr 1fr;
                visibility: hidden;
                opacity: 0;
                transition: opacity 0.2s ease, visibility 0.2s ease;
                transition-delay: 0.6s; 
                position: absolute;
                bottom: 100%; 
                left: 0;
                width: 100%;
                background-color: white;
                box-shadow: 0px -8px 20px 0px rgba(0,0,0,0.2); 
                z-index: 1000;
                border-radius: 8px;
                border: 1px solid #bdc3c7;
                margin-bottom: 5px; 
                max-height: 250px;
                overflow-y: auto; 
            }
            
            .custom-dropdown:hover .dropdown-menu {
                visibility: visible;
                opacity: 1;
                transition-delay: 0s; 
            }

            .dropdown-item {
                padding: 10px 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                border-bottom: 1px solid #ecf0f1;
                border-right: 1px solid #ecf0f1;
                color: #2c3e50;
                font-weight: 500;
                font-size: 13px;
                background-color: white;
                transition: background-color 0.1s;
            }
            .dropdown-item:nth-child(even) { border-right: none; }
            .dropdown-item:hover { background-color: #f1f2f6; }
            
            .mode-icon { display: inline-flex; align-items: center; justify-content: center; }
            .mode-icon svg { width: 20px; height: 20px; }
        </style>
        
        <div class="visu-container">
            $mainOverview
            
            <div id="modal_buffer" class="modal-overlay">
                <div class="modal-content">
                    <div class="close-btn" onclick="closeModal('modal_buffer')">&times;</div>
                    <div class="modal-body" style="overflow:hidden;">$popupBufferContent</div>
                </div>
            </div>
            
            <div id="modal_boiler" class="modal-overlay">
                <div class="modal-content" style="max-width: 400px; max-height: 300px;">
                    <div class="close-btn" onclick="closeModal('modal_boiler')">&times;</div>
                    <div class="modal-body" style="text-align:center; padding-top:40px;">
                        <h2>Kessel Status</h2>
                        <div style="font-size: 40px; margin: 20px 0;" id="detail_boil_temp">-- °C</div>
                        <div id="detail_boil_state" style="font-size: 20px; font-weight:bold; color:#7f8c8d;">AUS</div>
                    </div>
                </div>
            </div>

            $modalsHTML
        </div>

        <script>
            var initialData = $initialData;
            var modeMap = $modeMapJSON; 
            var iconMap = $iconMapJSON; 
            var opModeMap = $opModeMapJSON; // NEU

            setTimeout(function() { updateView(initialData); }, 50);

            function handleMessage(data) {
                var jsonObj = JSON.parse(data);
                updateView(jsonObj);
            }

            function updateView(data) {
                if (!data) return;

                if(data.t_out !== undefined) {
                    setText('main_out_temp', fmt(data.t_out));
                }
                if(data.t_out_avg !== undefined) {
                    setText('main_out_avg_temp', fmt(data.t_out_avg));
                }
                
                if(data.fill !== undefined) {
                    document.documentElement.style.setProperty('--fill-val', data.fill);
                    setText('main_buf_fill', parseFloat(data.fill).toFixed(0));
                    var tankSvg = document.getElementById('tankSvg');
                    if (tankSvg) tankSvg.style.setProperty('--fill-val', Math.round(data.fill));
                }
                var tankSvg = document.getElementById('tankSvg');
                if (tankSvg) {
                    if(data.t_boil !== undefined) tankSvg.style.setProperty('--t-boiler', Math.round(data.t_boil));
                    if(data.t_p3 !== undefined)   tankSvg.style.setProperty('--t-puffer3', Math.round(data.t_p3));
                    if(data.t_p2 !== undefined)   tankSvg.style.setProperty('--t-puffer2', Math.round(data.t_p2));
                    if(data.t_p1 !== undefined)   tankSvg.style.setProperty('--t-puffer1', Math.round(data.t_p1));
                }

                if(data.ov_boil_temp !== undefined) {
                    setText('main_boil_temp', fmt(data.ov_boil_temp));
                    setText('detail_boil_temp', fmt(data.ov_boil_temp) + " °C");
                }
                if(data.ov_boil_state !== undefined) {
                    var state = parseInt(data.ov_boil_state);
                    var flame = document.getElementById('flame_icon');
                    var warnIcon = document.getElementById('warning_icon');
                    var alertIcon = document.getElementById('alert_icon');
                    var detState = document.getElementById('detail_boil_state');
                    var boilRect = document.getElementById('main_boiler_rect');
                    var statusText = document.getElementById('boiler_status_text');

                    function resetIcons() {
                        if(flame) { flame.classList.remove('flame-active'); flame.style.opacity = 0; }
                        if(warnIcon) { warnIcon.classList.remove('blink-active'); warnIcon.style.opacity = 0; }
                        if(alertIcon) { alertIcon.classList.remove('blink-active'); alertIcon.style.opacity = 0; }
                    }
                    resetIcons();

                    var bColor = "#95a5a6"; var sText = "Aus"; var sColor = "#7f8c8d";

                    switch(state) {
                        case 1: bColor = "#95a5a6"; sText = "Aus"; break;
                        case 6: bColor = "#d35400"; sText = "Leistungsbrand"; sColor = "#e74c3c";
                                if(flame) { flame.classList.add('flame-active'); flame.setAttribute("fill", "#f9ca24"); } break;
                        case 2: bColor = "#95a5a6"; sText = "Zündung warten";
                                if(flame) { flame.style.opacity = 1; flame.setAttribute("fill", "#7f8c8d"); } break;
                        case 3: bColor = "#95a5a6"; sText = "Zündung";
                                if(flame) { flame.style.opacity = 1; flame.setAttribute("fill", "#f9ca24"); } break;
                        case 4: bColor = "url(#boilerHalfGradient)"; sText = "Anheizen";
                                if(flame) { flame.style.opacity = 1; flame.setAttribute("fill", "#f9ca24"); } break;
                        case 9: bColor = "#d35400"; sText = "Ausbrand"; break;
                        case 11: bColor = "url(#boilerHalfGradient)"; sText = "Restwärme"; break;
                        case 12: bColor = "#c0392b"; sText = "Übertemperatur!"; sColor = "#c0392b";
                                 if(warnIcon) warnIcon.classList.add('blink-active'); break;
                        case 13: bColor = "#d35400"; sText = "Türe offen!"; sColor = "#e74c3c";
                                 if(alertIcon) alertIcon.classList.add('blink-active'); break;
                    }
                    if(boilRect) boilRect.setAttribute("fill", bColor);
                    if(statusText) statusText.textContent = sText;
                    if(detState) { detState.textContent = sText; detState.style.color = sColor; }
                }

                if(data.circuits) {
                    data.circuits.forEach(function(c) {
                        setText('val_target_temp_' + c.id, fmt(c.target_temp));
                        setText('detail_target_temp_' + c.id, fmt(c.target_temp));
                        setText('detail_flow_temp_' + c.id, fmt(c.temp));
                        
                        var isPumpOn = (c.state == true || c.state == 1);
                        var pumpIcon = document.getElementById('pump_icon_' + c.id);
                        var detailState = document.getElementById('detail_state_' + c.id);
                        
                        if(isPumpOn) {
                            if(pumpIcon) { pumpIcon.classList.remove('pump-inactive'); pumpIcon.classList.add('pump-active'); }
                            if(detailState) { detailState.innerText = "Pumpe LÄUFT"; detailState.style.color = "#27ae60"; }
                        } else {
                            if(pumpIcon) { pumpIcon.classList.remove('pump-active'); pumpIcon.classList.add('pump-inactive'); }
                            if(detailState) { detailState.innerText = "Pumpe AUS"; detailState.style.color = "#7f8c8d"; }
                        }

                        // Gewählter Modus (Soll)
                        if(c.mode !== -1) {
                            var modeName = modeMap[c.mode] || "Unbekannt";
                            var modeIcon = iconMap[c.mode] || "";

                            setText('main_mode_text_' + c.id, "Betriebsmodus: " + modeName);

                            var currentTextEl = document.getElementById('current_text_' + c.id);
                            var currentIconEl = document.getElementById('current_icon_' + c.id);
                            
                            if(currentTextEl) currentTextEl.textContent = modeName;
                            if(currentIconEl) currentIconEl.innerHTML = modeIcon;
                        }

                        // NEU: Aktueller Status (Ist)
                        if(c.op_mode !== -1) {
                            var opModeName = opModeMap[c.op_mode] || "Unbekannt";
                            setText('main_opmode_text_' + c.id, "HK Heizmodus: " + opModeName);
                        }
                    });
                }
            }
            function fmt(val) { return parseFloat(val).toFixed(1); }
            function setText(id, val) { var el = document.getElementById(id); if(el) el.textContent = val; }
            function openModal(id) { document.getElementById(id).style.display = 'flex'; }
            function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        </script>
HTML;
        return $html;
    }

    private function getBufferPopupSVG() {
        return '
        <svg width="100%" height="100%" viewBox="0 0 450 650" xmlns="http://www.w3.org/2000/svg" id="tankSvg">
          <defs>
            <linearGradient id="coldWater" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3498db"/><stop offset="100%" stop-color="#2980b9"/></linearGradient>
            <linearGradient id="hotWaterFade" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#e74c3c" stop-opacity="1"/><stop offset="80%" stop-color="#e74c3c" stop-opacity="1"/><stop offset="100%" stop-color="#e74c3c" stop-opacity="0"/></linearGradient>
            <filter id="tankShadow" x="-5%" y="-5%" width="110%" height="110%"><feGaussianBlur in="SourceAlpha" stdDeviation="3"/><feOffset dx="2" dy="2" result="offsetblur"/><feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge></filter>
            <clipPath id="tankShape"><rect x="30" y="30" width="220" height="560" rx="20" ry="20" /></clipPath>
          </defs>
          <style>
            #tankSvg { --fill-val: 0; --t-boiler: 0; --t-puffer3: 0; --t-puffer2: 0; --t-puffer1: 0; }
            #tankSvg text { font-family: "Helvetica Neue", Arial, sans-serif; fill: #2c3e50; }
            .tank-outline { fill: none; stroke: #34495e; stroke-width: 4; filter: url(#tankShadow); }
            .layer-cold { fill: url(#coldWater); width: 220px; height: 560px; }
            .layer-hot { fill: url(#hotWaterFade); width: 220px; height: calc(var(--fill-val) * 1%); transition: height 0.5s ease; }
            .spindle-coil { fill: none; stroke: rgba(255, 255, 255, 0.6); stroke-width: 8; stroke-linecap: round; pointer-events: none; }
            .spindle-connector { stroke: #7f8c8d; stroke-width: 8; fill: none; }
            .sensor-line { stroke: #2c3e50; stroke-width: 2; stroke-dasharray: 5, 3; }
            .sensor-head { fill: #e67e22; stroke: #2c3e50; stroke-width: 2; }
            .sensor-label { font-size: 14px; font-weight: 500; alignment-baseline: middle; }
            .sensor-label-bold { font-size: 15px; font-weight: bold; }
            .html-container { display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; font-family: "Helvetica Neue", Arial, sans-serif; background: transparent; }
            .percent-text::after { counter-reset: fillLevel var(--fill-val); content: counter(fillLevel) "%"; font-size: 40px; font-weight: 900; color: #000000; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: rgba(255, 255, 255, 0.5); }
            .temp-display { font-size: 12px; font-weight: bold; color: #e67e22; }
            .val-boiler::after  { counter-reset: c var(--t-boiler);  content: counter(c) "°C"; }
            .val-puffer3::after { counter-reset: c var(--t-puffer3); content: counter(c) "°C"; }
            .val-puffer2::after { counter-reset: c var(--t-puffer2); content: counter(c) "°C"; }
            .val-puffer1::after { counter-reset: c var(--t-puffer1); content: counter(c) "°C"; }
          </style>
          <g transform="translate(20, 20)">
            <g clip-path="url(#tankShape)"><rect x="30" y="30" class="layer-cold" /><rect x="30" y="30" class="layer-hot" /></g>
            <rect x="30" y="30" width="220" height="560" rx="20" ry="20" class="tank-outline" pointer-events="none"/>
            <line x1="10" y1="50" x2="60" y2="50" class="spindle-connector"/><line x1="10" y1="570" x2="60" y2="570" class="spindle-connector"/>
            <path class="spindle-coil" d="M 60 50 Q 220 50, 220 80 Q 220 110, 60 110 Q 60 140, 220 140 Q 220 170, 60 170 Q 60 200, 220 200 Q 220 230, 60 230 Q 60 260, 220 260 Q 220 290, 60 290 Q 60 320, 220 320 Q 220 350, 60 350 Q 60 380, 220 380 Q 220 410, 60 410 Q 60 440, 220 440 Q 220 470, 60 470 Q 60 500, 220 500 Q 220 530, 60 530 Q 60 570, 220 570 L 60 570" />
            <g transform="translate(0, 86)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;"><div xmlns="http://www.w3.org/1999/xhtml" class="html-container"><span class="temp-display val-boiler"></span></div></foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" /><circle cx="250" cy="0" r="6" class="sensor-head" /><text x="305" y="0" class="sensor-label sensor-label-bold">Boiler Fühler</text>
            </g>
            <g transform="translate(0, 230)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;"><div xmlns="http://www.w3.org/1999/xhtml" class="html-container"><span class="temp-display val-puffer3"></span></div></foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" /><circle cx="250" cy="0" r="6" class="sensor-head" /><text x="305" y="0" class="sensor-label">Pufferfühler 3</text>
            </g>
            <g transform="translate(0, 380)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;"><div xmlns="http://www.w3.org/1999/xhtml" class="html-container"><span class="temp-display val-puffer2"></span></div></foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" /><circle cx="250" cy="0" r="6" class="sensor-head" /><text x="305" y="0" class="sensor-label">Pufferfühler 2</text>
            </g>
            <g transform="translate(0, 530)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;"><div xmlns="http://www.w3.org/1999/xhtml" class="html-container"><span class="temp-display val-puffer1"></span></div></foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" /><circle cx="250" cy="0" r="6" class="sensor-head" /><text x="305" y="0" class="sensor-label">Pufferfühler 1</text>
            </g>
            <foreignObject x="30" y="30" width="220" height="560" style="pointer-events:none;"><div xmlns="http://www.w3.org/1999/xhtml" class="html-container"><span class="percent-text"></span></div></foreignObject>
          </g>
        </svg>';
    }
}