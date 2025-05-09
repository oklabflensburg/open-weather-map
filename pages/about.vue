<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Über die MOSMIX Wetterkarte</h1>
    
    <p class="mb-4">
      Diese Anwendung stellt Wettervorhersagedaten des Deutschen Wetterdienstes (DWD) in einer interaktiven Karte dar. 
      Die Daten basieren auf dem MOSMIX-Vorhersagemodell, das eine hochpräzise Wettervorhersage für über 5.000 Standorte weltweit ermöglicht.
    </p>
    
    <h2 class="text-2xl font-semibold mt-6 mb-3">Was ist MOSMIX?</h2>
    <p class="mb-4">
      MOSMIX ist ein statistisches Nachbearbeitungsverfahren des Deutschen Wetterdienstes, das Vorhersagen verschiedener 
      numerischer Wettervorhersagemodelle (NWP) kombiniert und optimiert. Dieses Verfahren berechnet stationsbasierte 
      Punktvorhersagen für meteorologische Parameter wie Temperatur, Niederschlag, Wolkenbedeckung, Wind, 
      Sonnenscheindauer und Luftdruck, und bietet damit eine zuverlässigere Vorhersage als einzelne Modelle.
    </p>
    <p class="mb-4">
      Die MOSMIX-Daten werden zweimal täglich aktualisiert und umfassen Vorhersagen für einen Zeitraum von bis zu 10 Tagen 
      mit einer zeitlichen Auflösung von einer Stunde.
    </p>
    
    <h2 class="text-2xl font-semibold mt-6 mb-3">Funktionen</h2>
    <ul class="list-disc pl-6 mb-6 space-y-2">
      <li>
        <strong>Interaktive Wetterkarte</strong> - Visualisierung von Wetterstationen in Deutschland mit Clustering-Funktion 
        für bessere Übersichtlichkeit bei verschiedenen Zoomstufen
      </li>
      <li>
        <strong>Detaillierte Stationsprognosen</strong> - Umfangreiche Wetterinformationen für jede Station mit 
        stündlichen Vorhersagen für bis zu 24 Stunden
      </li>
      <li>
        <strong>Mehrere Wetterparameter</strong> - Anzeige von Temperatur, Niederschlag, Windgeschwindigkeit und -richtung, 
        Luftdruck, Wolkenbedeckung und Sonnenscheindauer
      </li>
      <li>
        <strong>Ortsuche</strong> - Schnelles Auffinden von Wetterdaten für bestimmte Orte und Regionen
      </li>
      <li>
        <strong>Responsive Design</strong> - Optimierte Darstellung für Desktop-Computer, Tablets und Smartphones
      </li>
      <li>
        <strong>Echtzeitaktualisierung</strong> - Automatische Aktualisierung der Daten basierend auf dem aktuellen 
        Kartenausschnitt
      </li>
    </ul>
    
    <h2 class="text-2xl font-semibold mt-6 mb-3">Technische Details</h2>
    <p class="mb-4">
      Die Anwendung nutzt eine moderne Web-Architektur:
    </p>
    <ul class="list-disc pl-6 mb-6">
      <li>Frontend entwickelt mit <strong>Nuxt.js 3</strong> und <strong>Vue.js 3</strong> unter Verwendung der Composition API</li>
      <li>Kartenvisualisierung mit <strong>Leaflet.js</strong> und <strong>Leaflet.markercluster</strong> für effiziente Marker-Gruppierung</li>
      <li>Responsive UI-Komponenten mit <strong>Tailwind CSS</strong></li>
      <li>Geocoding-Funktionalität über die <strong>Nominatim API</strong> für die Ortssuche</li>
      <li>Datenabruf über die <strong>MOSMIX API</strong> des OK Lab Flensburg, die auf offenen Daten des DWD basiert</li>
    </ul>
    
    <h2 class="text-2xl font-semibold mt-6 mb-3">Datenquellen</h2>
    <p class="mb-4">
      Die Wetterdaten werden vom Deutschen Wetterdienst (DWD) als Open Data bereitgestellt und über eine API des 
      OK Lab Flensburg aufbereitet und zur Verfügung gestellt. Der DWD ist der nationale meteorologische Dienst 
      der Bundesrepublik Deutschland und stellt hochwertige meteorologische Dienste für die Allgemeinheit und 
      verschiedene Nutzergruppen bereit.
    </p>
    <p class="mb-4">
      Die geographischen Daten für die Standortsuche werden von OpenStreetMap über die Nominatim-Schnittstelle 
      bezogen.
    </p>
    
    <h2 class="text-2xl font-semibold mt-6 mb-3">Credits</h2>
    <p class="mb-6">
      Wetterdaten bereitgestellt vom <a href="https://www.dwd.de/" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Deutschen Wetterdienst (DWD)</a>, 
      aufbereitet durch das <a href="https://oklabflensburg.de/" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">OK Lab Flensburg</a>. 
      Entwickelt mit Nuxt.js, Vue.js, Leaflet und Tailwind CSS.
    </p>
    
    <div class="mt-8">
      <NuxtLink to="/" class="text-blue-600 hover:text-blue-800 underline">
        Zurück zur Karte
      </NuxtLink>
    </div>
  </div>
</template>

<script setup>
// About page setup
import { usePageMode } from '~/composables/usePageMode'
const { setPageMode, currentPageMode } = usePageMode()


setPageMode('default')

</script>
