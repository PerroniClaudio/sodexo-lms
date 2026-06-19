const sortableList = document.querySelector('[data-language-levels-sortable-list]');

if (sortableList) {
    let draggedItem = null;
    let previousOrder = [];
    let isSaving = false;

    const getItems = () => Array.from(sortableList.querySelectorAll('[data-language-level-item]'));
    const getCurrentOrder = () => getItems().map((item) => Number(item.dataset.languageLevelId));
    const setSavingState = (saving) => {
        sortableList.classList.toggle('pointer-events-none', saving);
        sortableList.classList.toggle('opacity-70', saving);
    };

    const restoreOrder = (languageLevelIds) => {
        const itemsById = new Map(getItems().map((item) => [Number(item.dataset.languageLevelId), item]));

        languageLevelIds.forEach((languageLevelId) => {
            const item = itemsById.get(languageLevelId);

            if (item) {
                sortableList.appendChild(item);
            }
        });
    };

    const persistOrder = async () => {
        const reorderedLanguageLevelIds = getCurrentOrder();

        if (previousOrder.length === 0 || reorderedLanguageLevelIds.join(',') === previousOrder.join(',')) {
            return;
        }

        isSaving = true;
        setSavingState(true);

        try {
            await window.axios.patch(
                sortableList.dataset.reorderUrl,
                { language_levels: reorderedLanguageLevelIds },
                { headers: { Accept: 'application/json' } },
            );
            window.showFlash?.('success', 'Ordine livelli lingua aggiornato con successo.');
            window.location.reload();
        } catch (error) {
            restoreOrder(previousOrder);
            window.location.reload();
        } finally {
            isSaving = false;
            setSavingState(false);
            previousOrder = [];
        }
    };

    sortableList.addEventListener('dragover', (event) => {
        if (isSaving || draggedItem === null) {
            return;
        }

        event.preventDefault();

        const targetItem = event.target.closest('[data-language-level-item]');

        if (!targetItem || targetItem === draggedItem) {
            return;
        }

        const targetBounds = targetItem.getBoundingClientRect();
        const shouldInsertAfter = event.clientY > targetBounds.top + targetBounds.height / 2;

        if (shouldInsertAfter) {
            sortableList.insertBefore(draggedItem, targetItem.nextSibling);
        } else {
            sortableList.insertBefore(draggedItem, targetItem);
        }
    });

    getItems().forEach((item) => {
        item.addEventListener('dragstart', () => {
            if (isSaving) {
                draggedItem = null;

                return;
            }

            draggedItem = item;
            previousOrder = getCurrentOrder();
            item.classList.add('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
        });

        item.addEventListener('dragend', async () => {
            if (draggedItem === null) {
                return;
            }

            draggedItem.classList.remove('opacity-50', 'shadow-lg', 'ring-2', 'ring-primary/30');
            draggedItem = null;
            await persistOrder();
        });
    });
}
