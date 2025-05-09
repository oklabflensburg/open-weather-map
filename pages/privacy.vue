<template>
<div class="container mx-auto px-4 py-6 md:pt-12">
    <h2 class="text-2xl font-semibold  mb-3">Datenschutzerklärung</h2>
    <p class="mb-4">
      Das {{ company.name }}, {{ company.street }}, {{ company.zipCode }} {{ company.city }}, Deutschland (nachfolgend als "{{ company.name }}" oder "wir" bezeichnet) betreibt die im Internet unter https://{{ company.domain }} und den jeweils dazu gehörenden Unterverzeichnissen abrufbare Internetseite (nachfolgend die "Website" genannt).
    </p>
    <p class="mb-4">
      Das {{ company.name }} möchte Ihnen mit dieser Datenschutzerklärung erläutern, welche Daten in welcher Form verarbeitet werden, wenn Sie unsere Website besuchen. Hiermit kommen wir zugleich unserer Informationspflicht gem. Art. 13 Datenschutz-Grundverordnung (DSGVO) Ihnen gegenüber nach.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">I. Verantwortlicher und Kontaktmöglichkeiten des Datenschutzbeauftragten</h3>
    <p class="mb-4">
      Die datenschutzrechtlich Verantwortliche für die auf unserer Website stattfindenden Datenverarbeitungen ist das:
    </p>
    <address class="mb-4 not-italic ml-4" v-html="company.getFormattedAddress()"></address>

    <p class="mb-2 ml-4"><strong>E-Mail:</strong> {{ company.email }}</p>
    <p class="mb-4 ml-4"><strong>Telefon:</strong> {{ company.phone }}</p>

    <p class="mb-4">
      Unser Datenschutzbeauftragter ist erreichbar unter:
    </p>
    <address class="mb-4 not-italic ml-4" v-html="company.getFormattedAddress()"></address>

    <p class="mb-2 ml-4"><strong>E-Mail:</strong> {{ company.email }}</p>
    <p class="mb-4 ml-4"><strong>Telefon:</strong> {{ company.phone }}</p>

    <h3 class="text-xl font-semibold mt-4 mb-2">II. Personenbezogene Daten, Zwecke und Rechtsgrundlage der Datenverarbeitung</h3>
    <h4 class="text-lg font-medium mt-3 mb-2">1. Personenbezogene Daten</h4>
    <p class="mb-4">
      Nach der DSGVO sind personenbezogene Daten "alle Informationen, die sich auf eine identifizierte oder identifizierbare natürliche Person (im Folgenden "betroffene Person") beziehen; als identifizierbar wird eine natürliche Person angesehen, die direkt oder indirekt, insbesondere mittels Zuordnung zu einer Kennung wie einem Namen, zu einer Kennnummer, zu Standortdaten, zu einer Online-Kennung oder zu einem oder mehreren besonderen Merkmalen identifiziert werden kann, die Ausdruck der physischen, physiologischen, genetischen, psychischen, wirtschaftlichen, kulturellen oder sozialen Identität dieser natürlichen Person sind.
    </p>
    
    <h4 class="text-lg font-medium mt-3 mb-2">2. Allgemeine Nutzung der Website</h4>
    <p class="mb-4">
      Wir erheben und speichern im Zuge Ihres Besuchs auf unserer Website keine personenbezogenen Daten. Unbeschadet der vorstehenden grundsätzlichen Gegebenheiten registriert aber der Webserver unseres Hosters automatisch Zugriffe auf die Websites und dabei insbesondere Ihre IP-Adresse. Zudem legt unser Hoster zur Erhaltung der Systemsicherheit sog. Logfiles an. Diese Logfiles enthalten die folgenden Informationen:
    </p>
    <ul class="list-disc ml-8 mb-4">
      <li>die IP - Adresse</li>
      <li>Datum des Zugriffs</li>
      <li>die URL</li>
      <li>Inhalte auf die zugegriffen wurde sowie</li>
      <li>die übertragenen Informationen</li>
    </ul>
    <p class="mb-4">
      Diese Informationen bleiben für uns anonym. Ein Rückschluss auf eine Person ist somit nicht möglich.
    </p>
    <p class="mb-4">
      Nehmen Sie unter der auf unserer Website angegebenen E-Mail-Adresse Kontakt zu uns auf, teilen Sie uns zumindest Ihre E-Mail-Adresse mit, sowie gegebenenfalls weitere Informationen, die Sie in Ihrer E-Mail preisgeben. Damit wir Ihr Anliegen bearbeiten können, müssen wir diese Daten verarbeiten.
    </p>
    
    <h4 class="text-lg font-medium mt-3 mb-2">3. Zwecke und Rechtsgrundlage der Datenverarbeitung</h4>
    <p class="mb-4">
      Die Verarbeitung Ihrer IP-Adresse während des Verbindungsaufbaus erfolgt, damit wir Ihnen unsere Website zur Verfügung stellen können. Sie basiert auf Art. 6 Abs. 1 lit. f) DSGVO. Unser berechtigtes Interesse besteht im genannten Zweck.
    </p>
    <p class="mb-4">
      Die Verarbeitung im Rahmen einer Kontaktaufnahme erfolgt, damit wir Ihre Anfrage bearbeiten und beantworten könne. Die Rechtsgrundlage ist Art. 6 Abs. 1 lit. f) DSGVO. Unser berechtigtes Interesse liegt im soeben genannten Zweck.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">III. Empfänger der Daten</h3>
    <p class="mb-4">
      Die jeweiligen aktiven aktiven Projektbeteiligten haben ggf. Zugriff auf Ihre Daten, sofern diese für die Bearbeitung der Anliegen zuständig sind. Zudem setzen wir externe Dienstleister ein, soweit wir Leistungen nicht oder nicht sinnvoll selbst vornehmen können. Diese externen Dienstleister sind dabei vor allem Anbieter von IT-Dienstleistungen und Telekommunikationsdienste.
    </p>
    <p class="mb-4">
      Eine übermittlung in Drittstaaten findet grundsätzlich nicht und nur dann statt, soweit sie zur Ausführung Ihrer Aufträge erforderlich ist, gesetzlich vorgeschrieben ist oder Sie uns Ihre Einwilligung hierzu erteilt haben.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">IV. Speicherdauer</h3>
    <p class="mb-4">
      Ihre E-Mails und Kontaktaufnahmen speichern wir so lange, wie es zur Bearbeitung Ihrer Anfrage erforderlich ist und speichern Sie anschließend für einen Zeitraum von 3 Jahren, falls Sie sich bezugnehmend auf Ihre ursprüngliche Frage noch einmal an uns wenden.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">V. Betroffenenrechte</h3>
    <p class="mb-4">
      Die Datenschutz-Grundverordnung garantiert Ihnen gewisse Rechte, die Sie uns gegenüber - soweit die gesetzlichen Voraussetzungen vorliegen - geltend machen können.
    </p>
    <ul class="list-disc ml-8 space-y-2 mb-4">
      <li>
        <strong>Art. 15 DSGVO - Auskunftsrecht der betroffenen Person:</strong><br>
        Sie haben das Recht, von uns eine Bestätigung darüber zu verlangen, ob Sie betreffende personenbezogene Daten verarbeitet werden und wenn ja, welche dies sind sowie die näheren Umstände der Datenverarbeitung.
      </li>
      <li>
        <strong>Art. 16 DSGVO - Recht auf Berichtigung:</strong><br>
        Sie haben das Recht, von uns unverzüglich die Berichtigung Sie betreffender unrichtiger personenbezogener Daten zu verlangen. Dabei haben Sie unter Berücksichtigung der Zwecke der Verarbeitung auch das Recht, die Vervollständigung unvollständiger personenbezogener Daten - auch mittels einer ergänzenden Erklärung - zu verlangen.
      </li>
      <li>
        <strong>Art. 17 DSGVO - Recht auf Löschung:</strong><br>
        Sie haben das Recht, von uns zu verlangen, dass Sie betreffende personenbezogene Daten unverzüglich gelöscht werden. Beachten Sie hier bitte die unter Punkt II. 4 beschriebene Ausnahme
      </li>
      <li>
        <strong>Art. 18 DSGVO - Recht auf Einschränkung der Verarbeitung:</strong><br>
        Sie haben das Recht, von uns die Einschränkung der Verarbeitung zu verlangen.
      </li>
      <li>
        <strong>Art. 20 DSGVO - Recht auf Datenübertragbarkeit:</strong><br>
        Sie haben das Recht, im Falle der Verarbeitung aufgrund einer Einwilligung oder zur Erfüllung eines Vertrags, die Sie betreffenden personenbezogenen Daten, die Sie uns bereitgestellt haben, in einem strukturierten, gängigen und maschinenlesbaren Format zu erhalten, und diese Daten einem anderen Verantwortlichen ohne Behinderung durch uns zu übermitteln oder die Daten direkt an den anderen Verantwortlichen übermitteln zu lassen, soweit dies technisch machbar ist.
      </li>
      <li>
        <strong>Art. 21 DSGVO - Widerspruchsrecht:</strong><br>
        Sie haben das Recht, aus Gründen, die sich aus ihrer besonderen Situation ergeben, jederzeit gegen die Verarbeitung Sie betreffender personenbezogener Daten, die aufgrund eines berechtigten Interesses unsererseits oder zur Wahrung einer Aufgabe im öffentlichen Interesse erforderlich ist, oder die in Ausübung öffentlicher Gewalt erfolgt, Widerspruch einzulegen.
        <p class="mt-2">
          Legen Sie Widerspruch ein, werden wir Ihre personenbezogenen Daten nicht mehr verarbeiten, es sei denn, wir können zwingende schutzwürdige Gründe für die Verarbeitung nachweisen, die Ihre Interessen, Rechte und Freiheiten überwiegen, oder die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen.
        </p>
        <p class="mt-2">
          Soweit wir Ihre personenbezogenen Daten verarbeiten, um Direktwerbung zu betreiben, haben Sie das Recht, jederzeit Widerspruch gegen die Verarbeitung einzulegen. Widersprechen Sie der Verarbeitung für Zwecke der Direktwerbung, so werden wir Ihre personenbezogenen Daten nicht mehr für diese Zwecke verarbeiten.
        </p>
      </li>
      <li>
        <strong>Art. 77 DSGVO i. V. m. § 19 BDSG - Recht auf Beschwerde bei einer Aufsichtsbehörde:</strong><br>
        Sie haben das Recht, jederzeit Beschwerde bei einer Aufsichtsbehörde, insbesondere in dem Mitgliedstaat ihres Aufenthaltsorts, ihres Arbeitsplatzes oder des Orts des mutmaßlichen Verstoßes, einzulegen, wenn Sie der Ansicht sind, dass die Verarbeitung der sie betreffenden personenbezogenen Daten gegen geltendes Recht verstößt.
      </li>
    </ul>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">VI. Pflicht zur Bereitstellung von Daten</h3>
    <p class="mb-4">
      Sie haben keine vertragliche oder gesetzliche Pflicht uns personenbezogene Daten bereitzustellen. Allerdings sind wir ohne die von Ihnen mitgeteilten Daten nicht in der Lage, Ihnen unsere Services anzubieten.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">VII. Bestehen von automatisierten Entscheidungsfindungen (einschließlich Profiling)</h3>
    <p class="mb-4">
      Wir verwenden keine automatisierten Entscheidungsfindungen, die Ihnen gegenüber rechtliche Wirkungen entfaltet oder Sie beeinträchtigt.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">VIII. Internetspezifische Datenverarbeitungen</h3>
    <p class="mb-2">
      <strong>1. Matomo:</strong> Während der Nutzung unserer Internetseiten werden durch uns automatisch technische Zugriffsdaten aufgezeichnet und ausgewertet. Hierfür benutzen wir das Tool "Matomo", ein Open-Source-Programm für Webanalytik, welches statistische Kennwerte zur Nutzung unserer Website auswertet. Diese Daten können jedoch nicht einer bestimmten Person zugeordnet werden; der einzelne Benutzer bleibt hierbei anonym. Weitere Informationen zu "Matomo" finden Sie auf der Website https://matomo.org.
    </p>
    <p class="mb-4">
      Zu diesen Daten gehören zum Beispiel
    </p>
    <ul class="list-disc ml-8 mb-4">
      <li>Die um die letzten beiden Oktette gekürzte IP-Adresse,</li>
      <li>Informationen über den verwendeten Internet-Browser und das verwendete Betriebssystem,</li>
      <li>der Domain-Name der Website, von der aus ein Besuch unserer Internet-Seiten erfolgt,</li>
      <li>die durchschnittliche Verweildauer in unserem Angebot und</li>
      <li>die in unserer Web-Site aufgerufenen Seiten.</li>
    </ul>
    <p class="mb-4">
      Durch die Entfernung des folgenden Hakens können Sie verhindern, dass wir mittels Matomo Ihre Besuche zählen. In diesem Fall wird ein Cookie gesetzt um uns zu signalisieren, dass Sie der Nutzung widersprochen haben.
    </p>
    <p class="mb-4">
      Bitte beachten Sie, dass die jeweiligen Einstellungen bzgl. Cookies nur für Ihr aktuell benutztes Gerät und Ihren aktuell benutzten Browser wirksam sind. Sollten Sie ein anderes Gerät oder einen anderen Browser benutzen, müssen Sie in der Regel die Einstellungen erneut vornehmen. Zudem unterstützen wir die "Do not Track" Funktion Ihres Browsers. Hiermit können Sie uns aktiv über Ihre Browsereinstellungen mitteilen, ob Sie eine Aufzeichnung Ihrer Aktivitäten wünschen oder nicht. Haben Sie "Do not Track" aktiviert, erfolgt keine Aufzeichnung Ihrer Aktivitäten.
    </p>
    <p class="mb-4">
      Die Rechtsgrundlage für die Verwendung von Matomo findet sich in Art. 6 Abs. 1 lit. f) DSGVO. Unser berechtigtes Interesse besteht darin, dass wir die Nutzung unserer Website zu deren Verbesserung und Optimierung auswerten. Die so erfassten Daten werden für einen Zeitraum von 24 Monaten gespeichert.
    </p>
    
    <p class="mb-2">
      <strong>2. Einbindung von Youtube Videos:</strong> Wir haben YouTube-Videos in unser Online-Angebot eingebunden. YouTube ist ein Broadcasting-Dienst, der uns das kostenlose Einstellen und Verlinken von Videoclips zur Darstellung unseres Unternehmens ermöglicht. Der Dienst auf youtube.com wird bereitgestellt von der Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland ("Google").
    </p>
    <p class="mb-4">
      Die Videos in unserem Online-Angebot sind auf youtube.com gespeichert und von unserer Website aus direkt abspielbar. Die Videos sind alle im "erweiterten Datenschutz-Modus" / "Zwei-Klick-Lösung" eingebunden. Das bedeutet, dass keine Daten über Sie als Nutzer an YouTube übertragen werden, wenn Sie die Videos nicht abspielen. Erst wenn Sie die Videos abspielen, werden personenbezogene Daten gemäß Absatz 2 an Google übertragen und wie dort dargestellt verarbeitet. Auf diese Datenübertragung und die weitere Verarbeitung durch Google haben wir keinen Einfluss.
    </p>
    <p class="mb-4">
      Durch den Besuch auf der Website und das Abspielen der Videos erhält Google die Information, dass Sie die entsprechende Unterseite unserer Website aufgerufen haben. Zudem werden die unter Ziffer 2 dieser Datenschutzerklärung genannten Daten an Google übermittelt. Dies erfolgt unabhängig davon, ob YouTube ein Nutzerkonto über Sie führt, in das Sie mittels Google eingeloggt sind, oder ob kein Nutzerkonto besteht. Wenn Sie bei Google eingeloggt sind, werden Ihre Daten direkt Ihrem Konto zugeordnet. Wenn Sie die Zuordnung mit Ihrem Profil bei YouTube nicht wünschen, müssen Sie sich vor Betätigung des Play-Buttons ausloggen. Google speichert Ihre Daten als Nutzungsprofile und nutzt sie für Zwecke der Werbung, Marktforschung und/oder bedarfsgerechten Gestaltung seiner Dienste. Eine solche Auswertung erfolgt insbesondere (auch für nicht eingeloggte Nutzer) zur Erbringung von bedarfsgerechter Werbung und um andere Nutzer des sozialen Netzwerks über Ihre Aktivitäten auf unserer Website zu informieren, wobei uns die Einzelheiten unbekannt sind. Ihnen steht ein Widerspruchsrecht zu gegen die Bildung dieser Nutzerprofile, wobei Sie sich zur Ausübung dessen an Google richten müssen. Im übrigen gelten für Sie die unter Ziffer 5 dieser Datenschutzerklärung genannten Betroffenenrechte.
    </p>
    <p class="mb-4">
      Weitere Informationen zu Zweck und Umfang der Datenerhebung und ihrer Verarbeitung durch Google erhalten Sie in den Datenschutzhinweisen von Google. Dort erhalten Sie auch weitere Informationen zu Ihren Rechten und Einstellungsmöglichkeiten zum Schutze Ihrer Privatsphäre. Google verarbeitet Ihre personenbezogenen Daten auch in den USA und hat sich zur Wahrung eines angemessenen Datenschutzniveaus iSd Art. 44 ff DSGVO dem EU- US-Privacy-Shield unterworfen.
    </p>
    
    <h3 class="text-xl font-semibold mt-4 mb-2">IX. Wenn Sie Anmerkungen oder Fragen haben</h3>
    <p class="mb-4">
      Wir treffen alle erdenklichen Vorkehrungen zum Schutz und zur Sicherheit Ihrer Daten. Ihre Fragen und Kommentare zum Datenschutz sind uns willkommen, schreiben Sie einfach eine Mail an {{ company.email }}.
    </p>
    
    <p class="italic mt-8">{{ company.city }}, April 2025</p>

  </div>
</template>

<script setup>
// Import company info composable
import { useCompanyInfo } from '~/composables/useCompanyInfo'
import { usePageMode } from '~/composables/usePageMode'
const { setPageMode, currentPageMode } = usePageMode()


setPageMode('default')
// Get company info
const company = useCompanyInfo()
</script>
