@extends('layouts.legal')

@section('eyebrow', 'Livraison et retours')
@section('heading')
    Livraison
    <br>
    <span class="italic">et retours.</span>
@endsection
@section('updated-at', '29 octobre 2026')

@section('legal-content')
    <p>
        Chaque kit Fragment est préparé à la main dans notre atelier, puis soigneusement emballé pour que toutes les pièces arrivent intactes chez vous.
    </p>

    <h3>Préparation de la commande</h3>
    <ul>
        <li><strong>Kits de la collection :</strong> expédiés sous 2 à 3 jours ouvrés.</li>
        <li><strong>Tableaux sur-mesure :</strong> fabriqués à la commande, expédiés sous 10 à 15 jours ouvrés.</li>
    </ul>
    <p>
        Vous recevez un e-mail avec le numéro de suivi dès que votre colis quitte l'atelier.
    </p>

    <h3>Zones et tarifs de livraison</h3>
    <ul>
        <li><strong>France métropolitaine</strong> — Colissimo suivi, 2 à 4 jours ouvrés : 6,90 €. Offerte dès 80 € d'achat.</li>
        <li><strong>Point relais (France)</strong> — 3 à 5 jours ouvrés : 4,90 €. Offerte dès 80 € d'achat.</li>
        <li><strong>Belgique, Luxembourg, Suisse</strong> — 4 à 7 jours ouvrés : 12,90 €.</li>
        <li><strong>Reste de l'Union européenne</strong> — 5 à 10 jours ouvrés : 16,90 €.</li>
    </ul>
    <p>
        Les frais exacts sont calculés et affichés avant le paiement. Pour une livraison hors de ces zones, écrivez-nous à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a>.
    </p>

    <h3>À la réception</h3>
    <p>
        Vérifiez l'état du colis en présence du livreur lorsque c'est possible. Si le carton est abîmé, ou s'il manque une pièce dans votre kit, envoyez-nous des photos dans les <strong>48 heures</strong> à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a>. Nous vous renvoyons gratuitement les pièces manquantes ou endommagées.
    </p>

    <h3>Retours</h3>
    <p>
        Vous disposez de <strong>14 jours</strong> après réception pour changer d'avis, sans avoir à vous justifier.
    </p>
    <h4>Comment retourner un article</h4>
    <ul>
        <li>Écrivez-nous à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a> en indiquant votre numéro de commande.</li>
        <li>Renvoyez le kit complet, non assemblé et dans son emballage d'origine, dans les 14 jours suivant votre demande.</li>
        <li>Les frais de retour sont à votre charge. Nous recommandons un envoi suivi.</li>
    </ul>
    <h4>Remboursement</h4>
    <p>
        Dès réception et vérification du colis, nous vous remboursons sous 14 jours, frais de livraison initiaux compris (sur la base du tarif standard), avec le moyen de paiement utilisé lors de la commande.
    </p>
    <h4>Articles non retournables</h4>
    <p>
        Les tableaux <strong>sur-mesure</strong>, fabriqués selon vos choix, ne peuvent être ni repris ni échangés, sauf défaut de fabrication. Les kits déjà assemblés, même partiellement, ne sont pas repris non plus.
    </p>

    <h3>Produit défectueux</h3>
    <p>
        Une pièce cassée, mal découpée ou un défaut de fabrication ? Contactez-nous avec des photos : nous remplaçons la pièce ou le produit à nos frais. Tous nos produits bénéficient des garanties légales détaillées dans nos <a href="{{ route('shop.terms') }}">conditions générales de vente</a>.
    </p>

    <h3>Une question ?</h3>
    <p>
        Notre atelier répond à toutes vos questions du lundi au vendredi à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a>.
    </p>
@endsection
