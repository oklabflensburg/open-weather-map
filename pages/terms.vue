<template>
<div class="container mx-auto px-4 pt-6 md:pt-12">
    <h1 class="text-3xl font-bold mb-6">Impressum</h1>

    <h2 class="text-xl font-semibold mt-6 mb-2">Adresse:</h2>
    <address class="mb-4 not-italic" v-html="company.getFormattedAddress()"></address>

    <p class="mb-2"><strong>E-Mail:</strong> {{ company.email }}</p>
    <p class="mb-6"><strong>Telefon:</strong> {{ company.phone }}</p>

    <h2 class="text-2xl font-semibold mt-6 mb-3">Haftungsausschluss</h2>
    <p class="mb-4">Inhaltlich verantwortlich gemäß § 55 Abs. 2 RStV ist {{ company.responsiblePerson }}.</p>

    <h3 class="text-xl font-semibold mt-4 mb-2">Haftung für Inhalte</h3>
    <p class="mb-4">
        Die Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach § 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen. Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.
    </p>

    <h3 class="text-xl font-semibold mt-4 mb-2">Haftung für Links</h3>
    <p class="mb-4">
        Unser Angebot enthält Links zu externen Webseiten Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar. Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.
    </p>

    <h3 class="text-xl font-semibold mt-4 mb-2">Urheberrecht</h3>
    <p class="mb-4">
        Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht, sofern diese nicht unter der CC0 1.0 Universell Lizenz veröffentlicht worden sind. Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
    </p>
</div>
</template>

<script setup>
// Import company info composable
import { useCompanyInfo } from '~/composables/useCompanyInfo'

// Get company info
const company = useCompanyInfo()
</script>
