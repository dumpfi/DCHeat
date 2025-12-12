<?php

declare(strict_types=1);

class HeizungskachelHTML extends IPSModule
{
    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        // Variable für Soll-Temperatur anlegen
        $this->RegisterVariableFloat("SollTemperatur", "Soll Temperatur", "~Temperature.Room", 1);
        $this->EnableAction("SollTemperatur");

        // WICHTIG: HTML-SDK aktivieren! (1 = HTML Kachel)
        $this->SetVisualizationType(1);
    }

    public function GetVisualizationTile()
    {
        // Initiales HTML senden.
        // Wir nutzen Flexbox, damit es zentriert ist.
        $html = '<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; background:#222; color:white;">';
        $html .= '  <h3 style="margin:0;">Wohnzimmer</h3>';
        $html .= '  <div id="display" style="font-size:3em; font-weight:bold;">--.- °C</div>';
        $html .= '  <div style="margin-top:10px;">';
        $html .= '    <button onclick="changeTemp(0.5)" style="font-size:1.5em; padding:10px 20px; margin:5px;">+</button>';
        $html .= '    <button onclick="changeTemp(-0.5)" style="font-size:1.5em; padding:10px 20px; margin:5px;">-</button>';
        $html .= '  </div>';
        $html .= '</div>';

        // Das Script für die Interaktion
        $html .= '<script>
            // Hört auf Nachrichten von Symcon (wenn sich der Wert ändert)
            function handleMessage(data) {
                console.log("Daten empfangen:", data);
                // JSON parsen, falls es als String kommt
                var val = parseFloat(data);
                if (!isNaN(val)) {
                     document.getElementById("display").innerText = val.toFixed(1) + " °C";
                }
            }

            // Sendet Aktion an Symcon
            function changeTemp(delta) {
                // Wir senden ein JSON Objekt an RequestAction
                var payload = { "action": "add", "value": delta };
                // "SollTemperatur" ist der Ident der Variable
                requestAction("SollTemperatur", JSON.stringify(payload));
            }
        </script>';

        return $html;
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == "SollTemperatur") {
            // $Value kommt hier als String an (vom JS JSON.stringify), also decodieren wir es
            $data = json_decode($Value, true);
            
            // Aktuellen Wert holen
            $currentTemp = $this->GetValue("SollTemperatur");
            
            // Neuen Wert berechnen
            $newTemp = $currentTemp + floatval($data['value']);
            
            // Wert setzen (das löst automatisch ein Update der Visu aus!)
            $this->SetValue("SollTemperatur", $newTemp);
        }
    }
    
    // Wird aufgerufen, wenn sich die Variable ändert -> Update an Kachel senden
    public function UpdateVisualizationValue($Value) {
        // Wir senden den neuen Wert an die Kachel
        $this->UpdateVisualizationValue(json_encode($Value));
    }
}