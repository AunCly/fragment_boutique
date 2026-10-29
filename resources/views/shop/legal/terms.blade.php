@extends('layouts.legal')

@section('eyebrow', 'CGV')
@section('heading', 'Conditions générales de vente')
@section('updated-at', '29 octobre 2026')

@section('legal-content')
    <h3>Article 1 — Objet et champ d'application</h3>
    <p>
        Les présentes conditions générales de vente (CGV) régissent les ventes de kits de tableaux en bois à assembler, de tableaux sur-mesure et d'accessoires réalisées sur le site Fragment par <strong>[Raison sociale]</strong>, [forme juridique] au capital de [montant] €, immatriculée au RCS de [ville] sous le n° [numéro], dont le siège est situé [adresse] (ci-après « Fragment »), auprès de consommateurs (ci-après « le Client »).
    </p>
    <p>
        Toute commande implique l'acceptation sans réserve des présentes CGV, qui prévalent sur tout autre document. Fragment se réserve le droit de les modifier à tout moment ; les CGV applicables sont celles en vigueur à la date de la commande.
    </p>

    <h3>Article 2 — Produits</h3>
    <p>
        Les produits proposés sont décrits avec la plus grande exactitude possible sur leur fiche (dimensions, essences de bois, contenu du kit, temps d'assemblage estimé). Le bois étant un matériau naturel, les veinages, nuances et teintes peuvent varier légèrement par rapport aux photographies, qui ne sont pas contractuelles.
    </p>
    <p>
        Les kits sont destinés à être assemblés par le Client. Ils contiennent de petites pièces et ne conviennent pas aux enfants de moins de 3 ans sans surveillance d'un adulte.
    </p>
    <p>
        Les tableaux sur-mesure sont réalisés selon les choix du Client (format, motif, essences) à l'aide de l'outil de personnalisation du site.
    </p>

    <h3>Article 3 — Prix</h3>
    <p>
        Les prix sont indiqués en euros toutes taxes comprises (TTC), hors frais de livraison. Les frais de livraison sont précisés avant la validation de la commande. Fragment se réserve le droit de modifier ses prix à tout moment ; les produits sont facturés au prix en vigueur lors de la validation de la commande.
    </p>

    <h3>Article 4 — Commande</h3>
    <p>
        Le Client sélectionne ses produits, les ajoute au panier puis valide sa commande après avoir vérifié son contenu, son prix total et corrigé d'éventuelles erreurs. La validation du paiement vaut conclusion du contrat. Un e-mail de confirmation récapitulant la commande est adressé au Client.
    </p>
    <p>
        Fragment se réserve le droit d'annuler toute commande d'un Client avec lequel existerait un litige relatif au paiement d'une commande antérieure.
    </p>

    <h3>Article 5 — Paiement</h3>
    <p>
        Le paiement est exigible immédiatement à la commande. Il s'effectue en ligne par carte bancaire ou par tout autre moyen proposé lors du paiement, via la plateforme sécurisée Shopify Payments. Fragment n'a jamais accès aux coordonnées bancaires du Client.
    </p>

    <h3>Article 6 — Livraison</h3>
    <p>
        Les produits sont livrés à l'adresse indiquée par le Client lors de la commande. Les zones desservies, délais et tarifs sont détaillés sur la page <a href="{{ route('shop.shipping-returns') }}">Livraison et retours</a>.
    </p>
    <p>
        En cas de retard de livraison supérieur à 30 jours par rapport à la date annoncée, le Client peut résoudre le contrat dans les conditions des articles L.216-6 et suivants du Code de la consommation.
    </p>
    <p>
        Le Client est invité à vérifier l'état du colis à réception et à signaler toute anomalie (colis endommagé, pièce manquante) dans les 48 heures à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a>, photos à l'appui.
    </p>

    <h3>Article 7 — Droit de rétractation</h3>
    <p>
        Conformément à l'article L.221-18 du Code de la consommation, le Client dispose d'un délai de <strong>14 jours</strong> à compter de la réception des produits pour exercer son droit de rétractation, sans avoir à justifier de motif.
    </p>
    <p>
        Pour l'exercer, le Client notifie sa décision par une déclaration dénuée d'ambiguïté à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a> ou en utilisant le formulaire de rétractation ci-dessous. Les produits doivent être retournés dans les 14 jours suivant cette notification, complets, non assemblés et dans leur emballage d'origine. Les frais de retour sont à la charge du Client.
    </p>
    <p>
        Fragment rembourse la totalité des sommes versées, y compris les frais de livraison initiaux (sur la base du mode de livraison standard), dans un délai de 14 jours à compter de la réception de la notification, ce remboursement pouvant être différé jusqu'à la récupération des produits.
    </p>
    <p>
        <strong>Exception :</strong> conformément à l'article L.221-28 3° du Code de la consommation, le droit de rétractation ne s'applique pas aux tableaux <strong>sur-mesure</strong>, confectionnés selon les spécifications du Client ou nettement personnalisés.
    </p>

    <h4>Formulaire de rétractation</h4>
    <p>
        À l'attention de [Raison sociale], [adresse], <a href="mailto:contact@fragment.fr">contact@fragment.fr</a> :<br>
        Je vous notifie par la présente ma rétractation du contrat portant sur la vente du bien ci-dessous :<br>
        Commandé le [date] / reçu le [date] — Numéro de commande : [numéro]<br>
        Nom du client : [nom] — Adresse : [adresse]<br>
        Date et signature (en cas de notification sur papier)
    </p>

    <h3>Article 8 — Garanties légales</h3>
    <p>
        Tous les produits bénéficient de la garantie légale de conformité (articles L.217-3 et suivants du Code de la consommation) et de la garantie des vices cachés (articles 1641 et suivants du Code civil).
    </p>
    <p>
        Au titre de la garantie de conformité, le Client dispose d'un délai de <strong>2 ans</strong> à compter de la délivrance du bien pour agir. Il peut choisir entre la réparation et le remplacement du bien, sous réserve des conditions de coût prévues par la loi. Il est dispensé de prouver l'existence du défaut durant ce délai.
    </p>
    <p>
        Au titre de la garantie des vices cachés, le Client peut agir dans un délai de 2 ans à compter de la découverte du vice et choisir entre la résolution de la vente ou une réduction du prix.
    </p>
    <p>
        Les garanties ne couvrent pas les dommages résultant d'un mauvais assemblage, d'une utilisation non conforme, d'une exposition prolongée à l'humidité ou à une source de chaleur, ni l'évolution naturelle de la teinte du bois dans le temps.
    </p>

    <h3>Article 9 — Responsabilité</h3>
    <p>
        Fragment ne saurait être tenu responsable de l'inexécution du contrat en cas de force majeure, de perturbation des services de transport ou du fait du Client.
    </p>

    <h3>Article 10 — Données personnelles</h3>
    <p>
        Les données collectées lors de la commande sont nécessaires à son traitement. Leur utilisation est détaillée dans les <a href="{{ route('shop.legal-notice') }}">mentions légales</a>.
    </p>

    <h3>Article 11 — Médiation et litiges</h3>
    <p>
        En cas de difficulté, le Client est invité à contacter en priorité le service client à <a href="mailto:contact@fragment.fr">contact@fragment.fr</a> afin de rechercher une solution amiable.
    </p>
    <p>
        À défaut, conformément aux articles L.611-1 et suivants du Code de la consommation, le Client peut recourir gratuitement au médiateur de la consommation : <strong>[Nom du médiateur]</strong>, [adresse], [site web].
    </p>
    <p>
        Les présentes CGV sont soumises au droit français. À défaut d'accord amiable, les tribunaux français sont compétents.
    </p>
@endsection
