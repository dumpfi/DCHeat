<?php

declare(strict_types=1);

// WICHTIG: Der Klassenname muss exakt so heißen, wie er in deiner module.json definiert ist 
// oder wie er vorher hieß. Ich habe ihn hier auf dein Original zurückgesetzt.
class HeizungskachelHTML extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // ---------------------------------------------------------------------
        // 1. Eigenschaften für den SPEICHER 
        // ---------------------------------------------------------------------
        $this->RegisterPropertyInteger("SourceFill", 0);       // (War vorher SourceFill) -> Mapping auf Buffer_FillLevel
        $this->RegisterPropertyInteger("SourceBoiler", 0);     // Mapping Buffer_TempTop
        $this->RegisterPropertyInteger("SourcePuffer3", 0);    // Mapping Buffer_TempMid
        $this->RegisterPropertyInteger("SourcePuffer2", 0);    // Mapping Buffer_TempBot
        
        // Alte Properties behalten wir, damit deine Einstellungen nicht verloren gehen,
        // oder wir mappen sie neu. Um Fehler zu vermeiden, registriere ich hier 
        // die neuen Namen, du musst sie in der Instanz dann neu auswählen.
        
        $this->RegisterPropertyInteger("Buffer_FillLevel", 0); 
        $this->RegisterPropertyInteger("Buffer_TempTop", 0);   
        $this->RegisterPropertyInteger("Buffer_TempMid", 0);   
        $this->RegisterPropertyInteger("Buffer_TempBot", 0);   

        // ---------------------------------------------------------------------
        // 2. Eigenschaften für den OFEN (Kessel)
        // ---------------------------------------------------------------------
        $this->RegisterPropertyInteger("Boiler_State", 0);     // Boolean: An/Aus
        $this->RegisterPropertyInteger("Boiler_Temp", 0);      // Temperatur Kessel

        // ---------------------------------------------------------------------
        // 3. Eigenschaften für den HEIZKREIS
        // ---------------------------------------------------------------------
        $this->RegisterPropertyInteger("Circuit_State", 0);    // Boolean: Pumpe An/Aus
        $this->RegisterPropertyInteger("Circuit_FlowTemp", 0); // Vorlauf Temperatur

        // HTML-SDK Kachel aktivieren
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Alte Nachrichten löschen
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) $this->UnregisterMessage($senderID, VM_UPDATE);
            }
        }

        // Alle konfigurierten Variablen überwachen
        // HINWEIS: Prüfe, ob du die alten "Source..." oder die neuen "Buffer..." nutzen willst.
        // Ich nutze hier im Array die NEUEN Namen. Du musst diese in der Instanz konfigurieren.
        $props = [
            "Buffer_FillLevel", "Buffer_TempTop", "Buffer_TempMid", "Buffer_TempBot",
            "Boiler_State", "Boiler_Temp",
            "Circuit_State", "Circuit_FlowTemp"
        ];

        foreach ($props as $prop) {
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
        // Hilfsfunktion: Versucht erst den neuen Property-Namen, wenn 0, dann Fallback auf alten Namen (für Speicher)
        $getVal = function($propName, $fallbackOldName = "") {
            $id = $this->ReadPropertyInteger($propName);
            
            // Fallback Logic für deine alten Variablen, damit du nicht alles neu einstellen musst
            if ($id == 0 && $fallbackOldName != "") {
                $id = $this->ReadPropertyInteger($fallbackOldName);
            }

            if ($id > 0 && IPS_VariableExists($id)) return GetValue($id);
            return 0; // Standardwert
        };

        $data = [
            // Speicher Werte (Mit Fallback auf deine alten Variablennamen "Source...")
            'buf_fill' => $getVal("Buffer_FillLevel", "SourceFill"),
            'buf_t1'   => $getVal("Buffer_TempTop", "SourceBoiler"),
            'buf_t2'   => $getVal("Buffer_TempMid", "SourcePuffer3"), 
            'buf_t3'   => $getVal("Buffer_TempBot", "SourcePuffer2"),
            
            // Ofen Werte
            'boil_state' => $getVal("Boiler_State"), 
            'boil_temp'  => $getVal("Boiler_Temp"),

            // Heizkreis Werte
            'circ_state' => $getVal("Circuit_State"),
            'circ_temp'  => $getVal("Circuit_FlowTemp"),
        ];

        return json_encode($data);
    }

    public function GetVisualizationTile()
    {
        $initialData = $this->GetAllValuesAsJSON();
        
        // --- Hier beginnt exakt der gleiche SVG Code wie im vorherigen Post ---
        // Ich kürze den SVG Inhalt hier nicht ab, damit du Copy&Paste machen kannst.
        
        $popupBufferContent = '
        <svg width="100%" height="100%" viewBox="0 0 450 650" id="tankSvg">
             <defs>
               <linearGradient id="coldWater" x1="0" y1="0" x2="0" y2="1">
                 <stop offset="0%" stop-color="#3498db"/><stop offset="100%" stop-color="#2980b9"/>
               </linearGradient>
               <linearGradient id="hotWaterFade" x1="0" y1="0" x2="0" y2="1">
                 <stop offset="0%" stop-color="#e74c3c" stop-opacity="1"/>
                 <stop offset="80%" stop-color="#e74c3c" stop-opacity="1"/>
                 <stop offset="100%" stop-color="#e74c3c" stop-opacity="0"/>
               </linearGradient>
               <clipPath id="tankShape"><rect x="30" y="30" width="220" height="560" rx="20" ry="20" /></clipPath>
             </defs>
             <g transform="translate(80, 20)"> 
                <g clip-path="url(#tankShape)">
                  <rect x="30" y="30" width="220" height="560" fill="url(#coldWater)" />
                  <rect x="30" y="30" width="220" height="560" fill="url(#hotWaterFade)" class="layer-hot" id="hotLayer" style="height: calc(var(--fill-val) * 1%); transition: height 0.8s ease;" />
                </g>
                <rect x="30" y="30" width="220" height="560" rx="20" ry="20" fill="none" stroke="#34495e" stroke-width="4"/>
                <text x="260" y="80" font-size="20" font-weight="bold" fill="#333">Oben: <tspan id="txt_buf_t1">--</tspan> °C</text>
                <text x="260" y="310" font-size="20" font-weight="bold" fill="#333">Mitte: <tspan id="txt_buf_t2">--</tspan> °C</text>
                <text x="260" y="550" font-size="20" font-weight="bold" fill="#333">Unten: <tspan id="txt_buf_t3">--</tspan> °C</text>
                <text x="140" y="300" text-anchor="middle" font-size="40" fill="white" font-weight="bold" style="text-shadow: 1px 1px 2px black;"><tspan id="txt_buf_fill">--</tspan>%</text>
             </g>
        </svg>';

        $mainOverview = '
        <svg viewBox="0 0 800 500" style="width:100%; height:100%;">
            <path d="M 220 250 L 350 250" stroke="#555" stroke-width="10" /> 
            <path d="M 470 200 L 600 200" stroke="#e74c3c" stroke-width="8" /> 
            <path d="M 470 300 L 600 300" stroke="#3498db" stroke-width="8" /> 

            <g class="clickable" onclick="openModal(\'modal_boiler\')">
                <rect x="50" y="150" width="170" height="200" rx="5" fill="#d35400" stroke="#a04000" stroke-width="3"/>
                <text x="135" y="190" text-anchor="middle" fill="white" font-weight="bold" font-size="18">OFEN</text>
                <path d="M 135 220 Q 155 260 135 300 Q 115 260 135 220" fill="#f1c40f" id="flame_icon" style="opacity: 0.2"/>
                <text x="135" y="330" text-anchor="middle" fill="white" font-size="16"><tspan id="main_boil_temp">--</tspan> °C</text>
            </g>

            <g class="clickable" onclick="openModal(\'modal_buffer\')">
                <rect x="350" y="100" width="120" height="300" rx="10" fill="#95a5a6" stroke="#7f8c8d" stroke-width="3"/>
                <rect x="350" y="100" width="120" height="300" rx="10" fill="url(#hotWaterFade)" id="main_puffer_anim" style="opacity:0.5"/>
                <text x="410" y="250" text-anchor="middle" fill="white" font-weight="bold" font-size="18">PUFFER</text>
                <text x="410" y="280" text-anchor="middle" fill="white" font-size="14"><tspan id="main_buf_fill">--</tspan> %</text>
            </g>

            <g class="clickable" onclick="openModal(\'modal_circuit\')">
                <rect x="600" y="150" width="150" height="200" rx="5" fill="#34495e" stroke="#2c3e50" stroke-width="3"/>
                <text x="675" y="190" text-anchor="middle" fill="white" font-weight="bold" font-size="18">HEIZUNG</text>
                <circle cx="675" cy="250" r="20" stroke="white" stroke-width="2" fill="none"/>
                <path d="M 675 250 L 690 235 L 690 265 Z" fill="white" id="pump_icon" transform-origin="675 250"/>
                <text x="675" y="330" text-anchor="middle" fill="white" font-size="16"><tspan id="main_circ_temp">--</tspan> °C</text>
            </g>
        </svg>';

        $html = <<<HTML
        <style>
            :root { --fill-val: 0; }
            .visu-container { position: relative; width: 100%; height: 100%; font-family: sans-serif; overflow: hidden; background: #ecf0f1; }
            .clickable { cursor: pointer; transition: opacity 0.2s; }
            .clickable:hover { opacity: 0.8; filter: brightness(1.1); }
            
            .modal-overlay {
                display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.6); z-index: 100;
                justify-content: center; align-items: center; backdrop-filter: blur(3px);
            }
            .modal-content {
                background: white; width: 90%; height: 90%; border-radius: 10px; position: relative;
                box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; flex-direction: column;
            }
            .close-btn {
                position: absolute; top: 10px; right: 15px; font-size: 30px; font-weight: bold; color: #e74c3c; cursor: pointer; z-index: 101;
            }
            .modal-body { flex: 1; padding: 10px; overflow: hidden; }

            @keyframes spin { 100% { transform: rotate(360deg); } }
            .pump-active { animation: spin 2s linear infinite; }
            .flame-active { opacity: 1 !important; fill: #e74c3c !important; filter: drop-shadow(0 0 5px #f1c40f); }
        </style>

        <div class="visu-container">
            $mainOverview

            <div id="modal_buffer" class="modal-overlay">
                <div class="modal-content">
                    <div class="close-btn" onclick="closeModal('modal_buffer')">&times;</div>
                    <div class="modal-body">$popupBufferContent</div>
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

            <div id="modal_circuit" class="modal-overlay">
                <div class="modal-content" style="max-width: 400px; max-height: 300px;">
                    <div class="close-btn" onclick="closeModal('modal_circuit')">&times;</div>
                    <div class="modal-body" style="text-align:center; padding-top:40px;">
                        <h2>Heizkreis</h2>
                        <div style="font-size: 40px; margin: 20px 0; color:#3498db;" id="detail_circ_temp">-- °C</div>
                        <div id="detail_circ_state" style="font-size: 20px;">Pumpe AUS</div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            var initialData = $initialData;
            updateView(initialData);

            function handleMessage(data) {
                var jsonObj = JSON.parse(data);
                updateView(jsonObj);
            }

            function updateView(data) {
                if (!data) return;
                
                // BUFFER
                if(data.buf_fill !== undefined) {
                    document.documentElement.style.setProperty('--fill-val', data.buf_fill);
                    setText('txt_buf_fill', parseFloat(data.buf_fill).toFixed(0));
                    setText('main_buf_fill', parseFloat(data.buf_fill).toFixed(0));
                }
                if(data.buf_t1 !== undefined) setText('txt_buf_t1', fmt(data.buf_t1));
                if(data.buf_t2 !== undefined) setText('txt_buf_t2', fmt(data.buf_t2));
                if(data.buf_t3 !== undefined) setText('txt_buf_t3', fmt(data.buf_t3));

                // BOILER
                if(data.boil_temp !== undefined) {
                    setText('main_boil_temp', fmt(data.boil_temp));
                    setText('detail_boil_temp', fmt(data.boil_temp) + " °C");
                }
                if(data.boil_state !== undefined) {
                    var isOn = (data.boil_state == true || data.boil_state == 1);
                    var flame = document.getElementById('flame_icon');
                    var detState = document.getElementById('detail_boil_state');
                    if(isOn) {
                        if(flame) flame.classList.add('flame-active');
                        if(detState) { detState.innerText = "BRENNER LÄUFT"; detState.style.color = "#e74c3c"; }
                    } else {
                        if(flame) flame.classList.remove('flame-active');
                        if(detState) { detState.innerText = "STANDBY"; detState.style.color = "#7f8c8d"; }
                    }
                }

                // CIRCUIT
                if(data.circ_temp !== undefined) {
                    setText('main_circ_temp', fmt(data.circ_temp));
                    setText('detail_circ_temp', fmt(data.circ_temp) + " °C");
                }
                if(data.circ_state !== undefined) {
                    var isPumpOn = (data.circ_state == true || data.circ_state == 1);
                    var pump = document.getElementById('pump_icon');
                    var detPump = document.getElementById('detail_circ_state');
                    if(isPumpOn) {
                        if(pump) pump.classList.add('pump-active');
                        if(detPump) { detPump.innerText = "Pumpe LÄUFT"; detPump.style.color = "#27ae60"; }
                    } else {
                        if(pump) pump.classList.remove('pump-active');
                        if(detPump) { detPump.innerText = "Pumpe AUS"; detPump.style.color = "#7f8c8d"; }
                    }
                }
            }

            function fmt(val) { return parseFloat(val).toFixed(1); }
            function setText(id, val) { var el = document.getElementById(id); if(el) el.innerText = val; }
            function openModal(id) { document.getElementById(id).style.display = 'flex'; }
            function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        </script>
HTML;
        return $html;
    }
}