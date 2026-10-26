(function () {
    var description = document.getElementById('description');
    var compteur = document.getElementById('compteur');
    if (description && compteur) {
        var maj = function () { compteur.textContent = String(description.value.length); };
        description.addEventListener('input', maj);
        maj();
    }

    var typeContrat = document.getElementById('type_contrat');
    var numero = document.getElementById('numero');
    var alerteSante = document.getElementById('alerte-sante');
    function sante() {
        if (!alerteSante) return;
        var prefixe = numero && numero.value.trim().toUpperCase().slice(0, 3);
        var actif = (typeContrat && typeContrat.value === 'sante') || prefixe === 'SAN';
        alerteSante.classList.toggle('cache', !actif);
    }
    if (numero) numero.addEventListener('input', sante);
    if (typeContrat) typeContrat.addEventListener('change', sante);

    var inputFichiers = document.getElementById('pieces');
    var liste = document.getElementById('liste-fichiers');
    var zone = document.getElementById('dropzone');
    function rendreFichiers() {
        if (!inputFichiers || !liste) return;
        liste.innerHTML = '';
        Array.prototype.forEach.call(inputFichiers.files, function (fichier, index) {
            var item = document.createElement('li');
            var nom = document.createElement('span');
            nom.textContent = fichier.name;
            var bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.textContent = 'Retirer';
            bouton.addEventListener('click', function () {
                var transfert = new DataTransfer();
                Array.prototype.forEach.call(inputFichiers.files, function (autre, i) {
                    if (i !== index) transfert.items.add(autre);
                });
                inputFichiers.files = transfert.files;
                rendreFichiers();
            });
            item.appendChild(nom);
            item.appendChild(bouton);
            liste.appendChild(item);
        });
    }
    if (inputFichiers) inputFichiers.addEventListener('change', rendreFichiers);
    if (zone && inputFichiers) {
        ['dragenter', 'dragover'].forEach(function (nom) {
            zone.addEventListener(nom, function (event) {
                event.preventDefault();
                zone.classList.add('survole');
            });
        });
        ['dragleave', 'drop'].forEach(function (nom) {
            zone.addEventListener(nom, function (event) {
                event.preventDefault();
                zone.classList.remove('survole');
            });
        });
        zone.addEventListener('drop', function (event) {
            if (!event.dataTransfer) return;
            inputFichiers.files = event.dataTransfer.files;
            rendreFichiers();
        });
    }

    var form = document.getElementById('form-reclamation');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        var valide = true;
        var premier = null;
        function marquer(id, message) {
            var champ = document.getElementById(id);
            if (!champ) return;
            valide = false;
            champ.classList.add('invalide');
            champ.setAttribute('aria-invalid', 'true');
            var aide = document.getElementById('err-' + id);
            if (!aide) {
                aide = document.createElement('p');
                aide.id = 'err-' + id;
                aide.className = 'erreur';
                champ.insertAdjacentElement('afterend', aide);
            }
            aide.textContent = message;
            if (!premier) premier = champ;
        }
        function nettoyer(id) {
            var champ = document.getElementById(id);
            if (!champ) return;
            champ.classList.remove('invalide');
            champ.removeAttribute('aria-invalid');
            var aide = document.getElementById('err-' + id);
            if (aide) aide.remove();
        }
        var nomValide = /^[\p{L}][\p{L} '\-]{1,49}$/u;
        ['nom', 'prenom'].forEach(function (id) {
            var champ = document.getElementById(id);
            if (!champ) return;
            if (!nomValide.test(champ.value.trim())) marquer(id, 'Veuillez saisir un nom valide.');
            else nettoyer(id);
        });
        var email = document.getElementById('email');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) marquer('email', "L'adresse e-mail n'est pas valide.");
        else nettoyer('email');
        var telephone = document.getElementById('telephone');
        if (telephone && telephone.value.trim() !== '' && !/^\d{10}$/.test(telephone.value.replace(/\s+/g, ''))) {
            marquer('telephone', 'Le numéro doit contenir 10 chiffres.');
        } else nettoyer('telephone');
        var numeroChamp = document.getElementById('numero');
        if (numeroChamp && !/^(AUT|HAB|SAN)-\d{6}$/i.test(numeroChamp.value.trim())) {
            marquer('numero', 'Numéro de contrat introuvable. Vérifiez votre attestation.');
        } else nettoyer('numero');
        var type = document.getElementById('type_contrat');
        var prefixe = numeroChamp ? numeroChamp.value.trim().toUpperCase().slice(0, 3) : '';
        var attendu = { AUT: 'auto', HAB: 'habitation', SAN: 'sante' }[prefixe];
        if (type && (type.value === '' || (attendu && type.value !== attendu))) {
            marquer('type_contrat', 'Le type ne correspond pas au numéro de contrat.');
        } else nettoyer('type_contrat');
        var categorie = document.getElementById('categorie');
        if (categorie && categorie.value === '') marquer('categorie', 'Choisissez une catégorie.');
        else nettoyer('categorie');
        var objet = document.getElementById('objet');
        if (objet && objet.value.trim() === '') marquer('objet', "L'objet est obligatoire.");
        else nettoyer('objet');
        var descriptionChamp = document.getElementById('description');
        if (descriptionChamp && (descriptionChamp.value.trim().length < 20 || descriptionChamp.value.trim().length > 2000)) {
            marquer('description', 'Décrivez votre réclamation (20 caractères minimum).');
        } else nettoyer('description');
        if (inputFichiers) {
            if (inputFichiers.files.length > 3) marquer('pieces', '3 fichiers maximum.');
            Array.prototype.forEach.call(inputFichiers.files, function (fichier) {
                var extension = fichier.name.split('.').pop().toLowerCase();
                if (['pdf', 'jpg', 'jpeg', 'png'].indexOf(extension) === -1) marquer('pieces', 'Format non accepté.');
                if (fichier.size > 5 * 1024 * 1024) marquer('pieces', 'Fichier trop volumineux (5 Mo max).');
            });
        }
        var consentement = document.getElementById('consentement');
        if (consentement && !consentement.checked) marquer('consentement', 'Votre accord est nécessaire pour traiter la réclamation.');
        else nettoyer('consentement');
        if (!valide) {
            event.preventDefault();
            if (premier) premier.focus();
            return;
        }
        var bouton = document.getElementById('envoyer');
        if (bouton) bouton.disabled = true;
    });
})();
(function () {
    var description = document.getElementById('description');
    var compteur = document.getElementById('compteur');
    if (description && compteur) {
        var maj = function () { compteur.textContent = String(description.value.length); };
        description.addEventListener('input', maj);
        maj();
    }

    var typeContrat = document.getElementById('type_contrat');
    var numero = document.getElementById('numero');
    var alerteSante = document.getElementById('alerte-sante');
    function sante() {
        if (!alerteSante) return;
        var prefixe = numero && numero.value.trim().toUpperCase().slice(0, 3);
        var actif = (typeContrat && typeContrat.value === 'sante') || prefixe === 'SAN';
        alerteSante.classList.toggle('cache', !actif);
    }
    if (numero) numero.addEventListener('input', sante);
    if (typeContrat) typeContrat.addEventListener('change', sante);

    var inputFichiers = document.getElementById('pieces');
    var liste = document.getElementById('liste-fichiers');
    var zone = document.getElementById('dropzone');
    function rendreFichiers() {
        if (!inputFichiers || !liste) return;
        liste.innerHTML = '';
        Array.prototype.forEach.call(inputFichiers.files, function (fichier, index) {
            var item = document.createElement('li');
            var nom = document.createElement('span');
            nom.textContent = fichier.name;
            var bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.textContent = 'Retirer';
            bouton.addEventListener('click', function () {
                var transfert = new DataTransfer();
                Array.prototype.forEach.call(inputFichiers.files, function (autre, i) {
                    if (i !== index) transfert.items.add(autre);
                });
                inputFichiers.files = transfert.files;
                rendreFichiers();
            });
            item.appendChild(nom);
            item.appendChild(bouton);
            liste.appendChild(item);
        });
    }
    if (inputFichiers) inputFichiers.addEventListener('change', rendreFichiers);
    if (zone && inputFichiers) {
        ['dragenter', 'dragover'].forEach(function (nom) {
            zone.addEventListener(nom, function (event) {
                event.preventDefault();
                zone.classList.add('survole');
            });
        });
        ['dragleave', 'drop'].forEach(function (nom) {
            zone.addEventListener(nom, function (event) {
                event.preventDefault();
                zone.classList.remove('survole');
            });
        });
        zone.addEventListener('drop', function (event) {
            if (!event.dataTransfer) return;
            inputFichiers.files = event.dataTransfer.files;
            rendreFichiers();
        });
    }

    var oeil = document.getElementById('voir-motdepasse');
    var motdepasse = document.getElementById('mot_de_passe');
    if (oeil && motdepasse) {
        oeil.addEventListener('click', function () {
            var visible = motdepasse.type === 'text';
            motdepasse.type = visible ? 'password' : 'text';
            oeil.setAttribute('aria-pressed', visible ? 'false' : 'true');
            oeil.setAttribute('aria-label', visible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
            oeil.innerHTML = visible ? '<i class="bi bi-eye" aria-hidden="true"></i>' : '<i class="bi bi-eye-slash" aria-hidden="true"></i>';
        });
    }
    var formConnexion = document.getElementById('form-connexion');
    if (formConnexion) {
        formConnexion.addEventListener('submit', function () {
            var bouton = document.getElementById('btn-connexion');
            if (bouton) bouton.disabled = true;
        });
    }

    var form = document.getElementById('form-reclamation');
    if (!form) return;
    form.addEventListener('submit', function (event) {
        var valide = true;
        var premier = null;
        function marquer(id, message) {
            var champ = document.getElementById(id);
            if (!champ) return;
            valide = false;
            champ.classList.add('invalide');
            champ.setAttribute('aria-invalid', 'true');
            var aide = document.getElementById('err-' + id);
            if (!aide) {
                aide = document.createElement('p');
                aide.id = 'err-' + id;
                aide.className = 'erreur';
                champ.insertAdjacentElement('afterend', aide);
            }
            aide.textContent = message;
            if (!premier) premier = champ;
        }
        function nettoyer(id) {
            var champ = document.getElementById(id);
            if (!champ) return;
            champ.classList.remove('invalide');
            champ.removeAttribute('aria-invalid');
            var aide = document.getElementById('err-' + id);
            if (aide) aide.remove();
        }
        var nomValide = /^[\p{L}][\p{L} '\-]{1,49}$/u;
        ['nom', 'prenom'].forEach(function (id) {
            var champ = document.getElementById(id);
            if (!champ) return;
            if (!nomValide.test(champ.value.trim())) marquer(id, 'Veuillez saisir un nom valide.');
            else nettoyer(id);
        });
        var email = document.getElementById('email');
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) marquer('email', "L'adresse e-mail n'est pas valide.");
        else nettoyer('email');
        var telephone = document.getElementById('telephone');
        if (telephone && telephone.value.trim() !== '' && !/^\d{10}$/.test(telephone.value.replace(/\s+/g, ''))) {
            marquer('telephone', 'Le numéro doit contenir 10 chiffres.');
        } else nettoyer('telephone');
        var numeroChamp = document.getElementById('numero');
        if (numeroChamp && !/^(AUT|HAB|SAN)-\d{6}$/i.test(numeroChamp.value.trim())) {
            marquer('numero', 'Numéro de contrat introuvable. Vérifiez votre attestation.');
        } else nettoyer('numero');
        var type = document.getElementById('type_contrat');
        var prefixe = numeroChamp ? numeroChamp.value.trim().toUpperCase().slice(0, 3) : '';
        var attendu = { AUT: 'auto', HAB: 'habitation', SAN: 'sante' }[prefixe];
        if (type && (type.value === '' || (attendu && type.value !== attendu))) {
            marquer('type_contrat', 'Le type ne correspond pas au numéro de contrat.');
        } else nettoyer('type_contrat');
        var categorie = document.getElementById('categorie');
        if (categorie && categorie.value === '') marquer('categorie', 'Choisissez une catégorie.');
        else nettoyer('categorie');
        var objet = document.getElementById('objet');
        if (objet && objet.value.trim() === '') marquer('objet', "L'objet est obligatoire.");
        else nettoyer('objet');
        var descriptionChamp = document.getElementById('description');
        if (descriptionChamp && (descriptionChamp.value.trim().length < 20 || descriptionChamp.value.trim().length > 2000)) {
            marquer('description', 'Décrivez votre réclamation (20 caractères minimum).');
        } else nettoyer('description');
        if (inputFichiers) {
            if (inputFichiers.files.length > 3) marquer('pieces', '3 fichiers maximum.');
            Array.prototype.forEach.call(inputFichiers.files, function (fichier) {
                var extension = fichier.name.split('.').pop().toLowerCase();
                if (['pdf', 'jpg', 'jpeg', 'png'].indexOf(extension) === -1) marquer('pieces', 'Format non accepté.');
                if (fichier.size > 5 * 1024 * 1024) marquer('pieces', 'Fichier trop volumineux (5 Mo max).');
            });
        }
        var consentement = document.getElementById('consentement');
        if (consentement && !consentement.checked) marquer('consentement', 'Votre accord est nécessaire pour traiter la réclamation.');
        else nettoyer('consentement');
        if (!valide) {
            event.preventDefault();
            if (premier) premier.focus();
            return;
        }
        var bouton = document.getElementById('envoyer');
        if (bouton) bouton.disabled = true;
    });
})();
