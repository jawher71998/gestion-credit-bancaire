document.addEventListener('DOMContentLoaded', function () {
  const typeSelect = document.getElementById('type-credit');
  const montantInput = document.getElementById('montant');
  const dureeInput = document.getElementById('duree');
  const montantVal = document.getElementById('montant-val');
  const dureeVal = document.getElementById('duree-val');
  const mensualiteEl = document.getElementById('mensualite');
  const demandeLink = document.getElementById('demande-link');

  function formatDT(n) {
    return Math.round(n).toLocaleString('fr-FR');
  }

  function calculerMensualite(montant, dureeMois, tauxAnnuel) {
    const tauxMensuel = (tauxAnnuel / 100) / 12;
    if (tauxMensuel === 0) return montant / dureeMois;
    const mensualite =
      (montant * tauxMensuel) / (1 - Math.pow(1 + tauxMensuel, -dureeMois));
    return mensualite;
  }

  function majAffichage() {
    const option = typeSelect.options[typeSelect.selectedIndex];
    const taux = parseFloat(option.dataset.taux);
    const min = parseInt(option.dataset.min, 10);
    const max = parseInt(option.dataset.max, 10);

    montantInput.min = min;
    montantInput.max = max;
    if (montantInput.value < min) montantInput.value = min;
    if (montantInput.value > max) montantInput.value = max;

    const montant = parseFloat(montantInput.value);
    const duree = parseInt(dureeInput.value, 10);

    montantVal.textContent = formatDT(montant) + ' DT';
    dureeVal.textContent = duree + ' mois';

    const mensualite = calculerMensualite(montant, duree, taux);
    mensualiteEl.textContent = formatDT(mensualite);

    if (demandeLink) {
      const params = new URLSearchParams({
        type: option.value,
        montant: Math.round(montant),
        duree: duree
      });
      demandeLink.href = 'demande.php?' + params.toString();
    }
  }

  typeSelect.addEventListener('change', majAffichage);
  montantInput.addEventListener('input', majAffichage);
  dureeInput.addEventListener('input', majAffichage);

  majAffichage();
});
