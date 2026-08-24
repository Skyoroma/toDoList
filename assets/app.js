import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

window.toggleModeSelection = function () {
    const wrappers = document.querySelectorAll('.selection-checkbox-wrapper');
    const bouton = document.getElementById('btn-toggle-selection');
    const barreDeSuppression = document.getElementById('form-suppression-groupee');

    wrappers.forEach(function (element) {
        element.classList.toggle('is-collapsed');
    });

    if (bouton.textContent.trim() === 'Sélectionner') {
        bouton.textContent = 'Annuler';
        barreDeSuppression.classList.remove('is-collapsed');
    } else {
        bouton.textContent = 'Sélectionner';
        barreDeSuppression.classList.add('is-collapsed');
        document.querySelectorAll('.custom-checkbox').forEach(function (checkbox) {
            checkbox.checked = false;
        });
    }
};

window.toggleModeReorder = function () {
    const handles = document.querySelectorAll('.drag-handle-wrapper');
    const bouton = document.getElementById('btn-toggle-reorder');

    document.querySelectorAll('.drag-item').forEach(function (element) {
        element.classList.remove('animate-fade-slide-up');
    });

    handles.forEach(function (element) {
        element.classList.toggle('is-collapsed');
    });

    bouton.textContent = bouton.textContent.trim() === 'Réorganiser' ? 'Terminer' : 'Réorganiser';
};

// Variables pour suivre l'élément en cours de glissement
let elementGlisse = null;

document.addEventListener('pointerdown', function (event) {
    const poignee = event.target.closest('.drag-handle-wrapper');
    if (!poignee) return; // clic ailleurs que sur une poignée : on ignore

    elementGlisse = poignee.closest('.drag-item');
    elementGlisse.setPointerCapture(event.pointerId);
    elementGlisse.classList.add('dragging');
});

let framePlanifiee = false;

document.addEventListener('pointermove', function (event) {
    if (!elementGlisse) return;
    if (framePlanifiee) return;

    framePlanifiee = true;
    requestAnimationFrame(function () {
        const liste = document.getElementById('liste-todos');
        const elementSurvole = document.elementsFromPoint(event.clientX, event.clientY)
            .find(el => el.classList.contains('drag-item') && el !== elementGlisse);

        if (elementSurvole) {
            const rect = elementSurvole.getBoundingClientRect();
            const milieu = rect.top + rect.height / 2;

            if (event.clientY < milieu) {
                liste.insertBefore(elementGlisse, elementSurvole);
            } else {
                liste.insertBefore(elementGlisse, elementSurvole.nextSibling);
            }
        }
        framePlanifiee = false;
    });
});

document.addEventListener('pointerup', function () {
    if (!elementGlisse) return;

    elementGlisse.classList.remove('dragging');
    elementGlisse = null;

    enregistrerNouvelOrdre();
});

function enregistrerNouvelOrdre() {
    const ids = Array.from(document.querySelectorAll('.drag-item')).map(function (element) {
        return element.dataset.todoId;
    });

    const params = new URLSearchParams();
    ids.forEach(function (id) {
        params.append('ordre[]', id);
    });

    fetch('/todo/reordonner', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    });
};

let suppressionsEnAttente = [];

window.supprimerTodo = function (id) {
    const carte = document.querySelector('[data-todo-id="' + id + '"]');
    if (carte) {
        carte.style.display = 'none';
    }

    fetch('/todo/supprimer/' + id, { method: 'POST', keepalive: true });

    afficherToastAnnulation(id);
};

window.afficherToastAnnulation = function (id) {
    const conteneur = document.getElementById('conteneur-toasts');

    const toast = document.createElement('div');
    toast.className = 'toast w-full max-w-xs rounded-lg bg-stone-800 dark:bg-stone-700 text-white text-sm px-4 py-3 shadow-lg flex items-center justify-between gap-3';
    toast.innerHTML = 'Tâche supprimée <button type="button" class="font-semibold text-violet-300 active:scale-95 transition-all">Annuler</button>';

    conteneur.appendChild(toast);

    const boutonAnnuler = toast.querySelector('button');

    const minuteur = setTimeout(function () {
        confirmerSuppression(id, toast);
    }, 5000);

    suppressionsEnAttente.push({ id: id, minuteur: minuteur, toast: toast });

    boutonAnnuler.addEventListener('click', function () {
        clearTimeout(minuteur);
        suppressionsEnAttente = suppressionsEnAttente.filter(function (item) {
            return item.id !== id;
        });
        restaurerTodo(id, toast);
    });
};

window.confirmerSuppression = function (id, toast) {
    fetch('/todo/confirmer-suppression/' + id, { method: 'POST', keepalive: true });
    suppressionsEnAttente = suppressionsEnAttente.filter(function (item) {
        return item.id !== id;
    });
    retirerToast(toast);
};

window.restaurerTodo = function (id, toast) {
    fetch('/todo/annuler-suppression/' + id, { method: 'POST', keepalive: true });

    const carte = document.querySelector('[data-todo-id="' + id + '"]');
    if (carte) {
        carte.style.display = '';
    }
    retirerToast(toast);
};

window.retirerToast = function (toast) {
    toast.classList.add('toast-sortant');
    setTimeout(function () {
        toast.remove();
    }, 250);
};

document.addEventListener('turbo:before-render', function () {
    suppressionsEnAttente.forEach(function (item) {
        clearTimeout(item.minuteur);
        confirmerSuppression(item.id, item.toast);
    });
});