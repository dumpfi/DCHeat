<?php

declare(strict_types=1);

class HeizungskachelHTML extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Wir registrieren Eigenschaften (Properties), in denen der User
        // die IDs seiner echten Variablen hinterlegen kann.
        $this->RegisterPropertyInteger("SourceFill", 0);
        $this->RegisterPropertyInteger("SourceBoiler", 0);
        $this->RegisterPropertyInteger("SourcePuffer3", 0);
        $this->RegisterPropertyInteger("SourcePuffer2", 0);
        $this->RegisterPropertyInteger("SourcePuffer1", 0);

        // HTML-SDK aktivieren
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // 1. Alte Nachrichten-Registrierungen löschen
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        // 2. Auf Änderungen der verknüpften Variablen hören
        $sources = [
            $this->ReadPropertyInteger("SourceFill"),
            $this->ReadPropertyInteger("SourceBoiler"),
            $this->ReadPropertyInteger("SourcePuffer3"),
            $this->ReadPropertyInteger("SourcePuffer2"),
            $this->ReadPropertyInteger("SourcePuffer1")
        ];

        foreach ($sources as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
    }

    // Diese Funktion wird aufgerufen, wenn sich eine der verknüpften Variablen ändert
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message == VM_UPDATE) {
            // Wir senden ein Update an die HTML-Kachel
            $this->UpdateVisualizationValue($this->GetAllValuesAsJSON());
        }
    }

    // Hilfsfunktion: Sammelt alle aktuellen Werte ein und baut ein JSON
    private function GetAllValuesAsJSON()
    {
        // Hilfsfunktion um sicher den Wert zu holen oder 0 zurückzugeben
        $getVal = function($propName) {
            $id = $this->ReadPropertyInteger($propName);
            if ($id > 0 && IPS_VariableExists($id)) {
                return GetValue($id);
            }
            return 0;
        };

        $data = [
            'fill'    => $getVal("SourceFill"),
            'boiler'  => $getVal("SourceBoiler"),
            'puffer3' => $getVal("SourcePuffer3"),
            'puffer2' => $getVal("SourcePuffer2"),
            'puffer1' => $getVal("SourcePuffer1"),
        ];

        return json_encode($data);
    }

    public function GetVisualizationTile()
    {
        // Wir holen uns die aktuellen Werte für den Startzustand
        $initialData = $this->GetAllValuesAsJSON();

        // Hier beginnt das SVG.
        $html = <<<HTML
        <div style="width:100%; height:100%; display:flex; justify-content:center; align-items:center; background: transparent;">
        <svg width="100%" height="100%" viewBox="0 0 450 650" xmlns="http://www.w3.org/2000/svg" id="tankSvg">
          <defs>
            <linearGradient id="coldWater" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#3498db"/>
              <stop offset="100%" stop-color="#2980b9"/>
            </linearGradient>

            <linearGradient id="hotWaterFade" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#e74c3c" stop-opacity="1"/>
              <stop offset="80%" stop-color="#e74c3c" stop-opacity="1"/>
              <stop offset="100%" stop-color="#e74c3c" stop-opacity="0"/>
            </linearGradient>

            <filter id="tankShadow" x="-5%" y="-5%" width="110%" height="110%">
              <feGaussianBlur in="SourceAlpha" stdDeviation="3"/>
              <feOffset dx="2" dy="2" result="offsetblur"/>
              <feMerge>
                <feMergeNode/>
                <feMergeNode in="SourceGraphic"/>
              </feMerge>
            </filter>

            <clipPath id="tankShape">
               <rect x="30" y="30" width="220" height="560" rx="20" ry="20" />
            </clipPath>
          </defs>

          <style>
            :root {
                --fill-val: 0; 
            }

            text { font-family: 'Helvetica Neue', Arial, sans-serif; fill: #2c3e50; }
            
            .tank-outline {
              fill: none; stroke: #34495e; stroke-width: 4; 
              filter: url(#tankShadow);
            }

            .layer-cold { fill: url(#coldWater); width: 220px; height: 560px; }

            .layer-hot {
              fill: url(#hotWaterFade);
              width: 220px;
              height: calc(var(--fill-val) * 1%); 
              transition: height 0.8s ease-in-out;
            }

            /* Spindel und Sensoren */
            .spindle-coil { fill: none; stroke: rgba(255, 255, 255, 0.6); stroke-width: 8; stroke-linecap: round; pointer-events: none; }
            .spindle-connector { stroke: #7f8c8d; stroke-width: 8; fill: none; }
            .sensor-line { stroke: #2c3e50; stroke-width: 2; stroke-dasharray: 5, 3; }
            .sensor-head { fill: #e67e22; stroke: #2c3e50; stroke-width: 2; }
            .sensor-label { font-size: 14px; font-weight: 500; alignment-baseline: middle; }
            .sensor-label-bold { font-size: 15px; font-weight: bold; }

            .html-container {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                height: 100%;
                font-family: 'Helvetica Neue', Arial, sans-serif;
                background: transparent;
            }

            .percent-text {
                font-size: 40px;
                font-weight: 900;
                color: #000000; 
                -webkit-text-stroke-width: 1px;
                -webkit-text-stroke-color: rgba(255, 255, 255, 0.5); 
            }

            .temp-display {
                font-size: 12px;
                font-weight: bold;
                color: #e67e22; 
            }
          </style>

          <g transform="translate(20, 20)">

            <g clip-path="url(#tankShape)">
              <rect x="30" y="30" class="layer-cold" />
              <rect x="30" y="30" class="layer-hot" id="hotLayer" />
            </g>
            
            <rect x="30" y="30" width="220" height="560" rx="20" ry="20" class="tank-outline" pointer-events="none"/>

            <line x1="10" y1="50" x2="60" y2="50" class="spindle-connector"/>
            <line x1="10" y1="570" x2="60" y2="570" class="spindle-connector"/>
            <path class="spindle-coil" d="M 60 50 Q 220 50, 220 80 Q 220 110, 60 110 Q 60 140, 220 140 Q 220 170, 60 170 Q 60 200, 220 200 Q 220 230, 60 230 Q 60 260, 220 260 Q 220 290, 60 290 Q 60 320, 220 320 Q 220 350, 60 350 Q 60 380, 220 380 Q 220 410, 60 410 Q 60 440, 220 440 Q 220 470, 60 470 Q 60 500, 220 500 Q 220 530, 60 530 Q 60 570, 220 570 L 60 570" />

            <g transform="translate(0, 86)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;">
                <div xmlns="http://www.w3.org/1999/xhtml" class="html-container">
                   <span id="val-boiler" class="temp-display">-- °C</span>
                </div>
              </foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" />
              <circle cx="250" cy="0" r="6" class="sensor-head" />
              <text x="305" y="0" class="sensor-label sensor-label-bold">Boiler Fühler</text>
            </g>

            <g transform="translate(0, 230)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;">
                <div xmlns="http://www.w3.org/1999/xhtml" class="html-container">
                   <span id="val-puffer3" class="temp-display">-- °C</span>
                </div>
              </foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" />
              <circle cx="250" cy="0" r="6" class="sensor-head" />
              <text x="305" y="0" class="sensor-label">Pufferfühler 3</text>
            </g>

            <g transform="translate(0, 380)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;">
                <div xmlns="http://www.w3.org/1999/xhtml" class="html-container">
                   <span id="val-puffer2" class="temp-display">-- °C</span>
                </div>
              </foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" />
              <circle cx="250" cy="0" r="6" class="sensor-head" />
              <text x="305" y="0" class="sensor-label">Pufferfühler 2</text>
            </g>

            <g transform="translate(0, 530)">
              <foreignObject x="245" y="-20" width="60" height="20" style="pointer-events:none;">
                <div xmlns="http://www.w3.org/1999/xhtml" class="html-container">
                   <span id="val-puffer1" class="temp-display">-- °C</span>
                </div>
              </foreignObject>
              <line x1="250" y1="0" x2="300" y2="0" class="sensor-line" />
              <circle cx="250" cy="0" r="6" class="sensor-head" />
              <text x="305" y="0" class="sensor-label">Pufferfühler 1</text>
            </g>

            <foreignObject x="30" y="30" width="220" height="560" style="pointer-events:none;">
                <div xmlns="http://www.w3.org/1999/xhtml" class="html-container">
                    <span id="val-percent" class="percent-text">-- %</span>
                </div>
            </foreignObject>

          </g>
        </svg>
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
                var svg = document.getElementById("tankSvg");
                if(data.fill !== undefined) {
                    svg.style.setProperty('--fill-val', data.fill);
                    document.getElementById("val-percent").innerText = parseFloat(data.fill).toFixed(0) + " %";
                }
                if(data.boiler !== undefined) document.getElementById("val-boiler").innerText = parseFloat(data.boiler).toFixed(1) + " °C";
                if(data.puffer3 !== undefined) document.getElementById("val-puffer3").innerText = parseFloat(data.puffer3).toFixed(1) + " °C";
                if(data.puffer2 !== undefined) document.getElementById("val-puffer2").innerText = parseFloat(data.puffer2).toFixed(1) + " °C";
                if(data.puffer1 !== undefined) document.getElementById("val-puffer1").innerText = parseFloat(data.puffer1).toFixed(1) + " °C";
            }
        </script>
HTML;

        return $html;
    }
}