document.documentElement.classList.add('js');

const shouldReduceEffects =
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
    || (typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4)
    || (typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4);

if (shouldReduceEffects) {
    document.documentElement.classList.add('reduced-effects');
}

const mobileMenu = document.getElementById('mobileMenu');
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const mobileMenuBackdrop = document.getElementById('mobileMenuBackdrop');
const mobileProfileToggle = document.getElementById('mobileProfileToggle');
const mobileProfilePanel = document.getElementById('mobileProfilePanel');
const mobileProfileMenu = document.querySelector('.profile-menu-mobile');

const photoAddForm = document.getElementById('photoAddForm');
const sourceUploadContainer = document.querySelector('[data-source-upload]');
const sourceUrlContainer = document.querySelector('[data-source-url]');
const photoSourceModeBlocks = document.querySelectorAll('[data-photo-source-mode]');
const videoUploadContainer = document.querySelector('[data-media-video-upload]');
const fileInput = document.getElementById('photo');
const videoInput = document.getElementById('video');
const urlInput = document.getElementById('photo_url');
const previewImage = document.getElementById('cardPreviewImage');
const focusXInput = document.getElementById('card_focus_x');
const focusYInput = document.getElementById('card_focus_y');
const focusXValue = document.getElementById('focusXValue');
const focusYValue = document.getElementById('focusYValue');
const videoFramePicker = document.getElementById('videoFramePicker');
const videoFramePlayer = document.getElementById('videoFramePlayer');
const videoFrameSeek = document.getElementById('video_frame_seek');
const captureVideoFrameBtn = document.getElementById('captureVideoFrameBtn');
const videoPreviewFrameInput = document.getElementById('video_preview_frame');
const videoFrameTimeValue = document.getElementById('videoFrameTimeValue');
const videoFrameNote = document.getElementById('videoFrameNote');

const liveSearchForm = document.getElementById('liveSearchForm');
const searchDataNode = document.getElementById('searchData');
const searchResultsContainer = document.getElementById('searchResultsContainer');
const searchResultsCount = document.getElementById('searchResultsCount');
const searchHint = document.getElementById('searchLiveHint');
const toggleFiltersBtn = document.getElementById('toggleFiltersBtn');
const filtersDropdown = document.getElementById('searchFiltersDropdown');
const clearFiltersBtn = document.getElementById('clearFiltersBtn');

const initSliderSpeeds = () => {
    const sliderTracks = document.querySelectorAll('[data-slider-track]');

    if (!sliderTracks.length) {
        return;
    }

    const speedPxPerSecond = 95;

    sliderTracks.forEach((track) => {
        const travelDistance = track.scrollWidth / 2;

        if (!travelDistance || !Number.isFinite(travelDistance)) {
            return;
        }

        const duration = Math.max(16, travelDistance / speedPxPerSecond);
        track.style.setProperty('--slider-duration', `${duration.toFixed(2)}s`);
    });
};

initSliderSpeeds();
window.addEventListener('load', initSliderSpeeds);
let sliderResizeTimer = null;
window.addEventListener('resize', () => {
    if (sliderResizeTimer) {
        window.clearTimeout(sliderResizeTimer);
    }

    sliderResizeTimer = window.setTimeout(initSliderSpeeds, 140);
});

const setMobileMenuState = (open) => {
    if (!mobileMenu || !mobileMenuToggle || !mobileMenuBackdrop) {
        return;
    }

    mobileMenu.classList.toggle('open', open);
    mobileMenuBackdrop.classList.toggle('show', open);
    mobileMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
    mobileMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');

};

if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', () => {
        const isOpen = mobileMenu.classList.contains('open');
        setMobileMenuState(!isOpen);
    });
}

if (mobileMenuBackdrop) {
    mobileMenuBackdrop.addEventListener('click', () => setMobileMenuState(false));
}

if (mobileMenu) {
    mobileMenu.querySelectorAll('a, button.logout-link').forEach((item) => {
        item.addEventListener('click', () => setMobileMenuState(false));
    });
}

const setMobileProfileState = (open) => {
    if (!mobileProfileToggle || !mobileProfilePanel) {
        return;
    }

    mobileProfilePanel.hidden = !open;
    mobileProfilePanel.classList.toggle('open', open);
    mobileProfileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
};

if (mobileProfileToggle && mobileProfilePanel) {
    mobileProfileToggle.addEventListener('click', () => {
        const isOpen = mobileProfileToggle.getAttribute('aria-expanded') === 'true';
        setMobileProfileState(!isOpen);
    });
}

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    setMobileProfileState(false);
    setMobileMenuState(false);
});

const handleOutsideProfileClick = (event) => {
    const rawTarget = event.target;
    const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;

    if (!target) {
        return;
    }

    if (target.closest('a.logout-link')) {
        return;
    }

    if (mobileProfileMenu && !target.closest('.profile-menu-mobile')) {
        setMobileProfileState(false);
    }
};

document.addEventListener('click', handleOutsideProfileClick);
document.addEventListener('touchstart', handleOutsideProfileClick, { passive: true });

const initRevealAnimations = () => {
    const revealNodes = document.querySelectorAll(
        '.panel, .gallery-section, .photo-card, .photo-view, .auth-card, .hero, .top-nav'
    );

    if (!revealNodes.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        revealNodes.forEach((node) => node.classList.add('visible'));
        return;
    }

    revealNodes.forEach((node, index) => {
        node.classList.add('reveal');
        node.style.setProperty('--delay', `${Math.min(index * 35, 260)}ms`);
    });

    const observer = new IntersectionObserver(
        (entries, currentObserver) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('visible');
                currentObserver.unobserve(entry.target);
            });
        },
        {
            rootMargin: '0px 0px -10% 0px',
            threshold: 0.08,
        }
    );

    revealNodes.forEach((node) => observer.observe(node));
};

initRevealAnimations();

if (photoAddForm) {
    const mediaTypeInputs = photoAddForm.querySelectorAll('input[name="media_type"]');
    const sourceInputs = photoAddForm.querySelectorAll('input[name="source_type"]');
    let selectedVideoObjectUrl = null;

    const formatSeconds = (value) => Number(value || 0).toFixed(1);

    const setVideoFrameMessage = (message) => {
        if (videoFrameNote) {
            videoFrameNote.textContent = message;
        }
    };

    const clearVideoFrameData = () => {
        if (videoPreviewFrameInput) {
            videoPreviewFrameInput.value = '';
        }

        if (videoFrameTimeValue) {
            videoFrameTimeValue.textContent = '0.0';
        }

        if (videoFrameSeek) {
            videoFrameSeek.value = '0';
            videoFrameSeek.disabled = true;
        }

        if (videoFramePlayer) {
            videoFramePlayer.removeAttribute('src');
            videoFramePlayer.load();
        }

        if (selectedVideoObjectUrl) {
            URL.revokeObjectURL(selectedVideoObjectUrl);
            selectedVideoObjectUrl = null;
        }
    };

    const prepareVideoFramePicker = () => {
        if (!videoInput || !videoFramePicker || !videoFramePlayer) {
            return;
        }

        if (!videoInput.files || !videoInput.files[0]) {
            clearVideoFrameData();
            videoFramePicker.hidden = true;
            setVideoFrameMessage('Загрузите видео, выберите момент и нажмите кнопку.');
            return;
        }

        const file = videoInput.files[0];
        clearVideoFrameData();
        selectedVideoObjectUrl = URL.createObjectURL(file);
        videoFramePlayer.src = selectedVideoObjectUrl;
        videoFramePlayer.muted = true;
        videoFramePlayer.currentTime = 0;
        videoFramePicker.hidden = false;
        setVideoFrameMessage('Выберите момент на шкале и нажмите «Взять кадр для превью».');
    };

    const selectedMediaType = () => {
        const selected = photoAddForm.querySelector('input[name="media_type"]:checked');
        return selected ? selected.value : 'photo';
    };

    const selectedSourceType = () => {
        const selected = photoAddForm.querySelector('input[name="source_type"]:checked');
        return selected ? selected.value : 'upload';
    };

    const updateMediaMode = () => {
        const mediaType = selectedMediaType();
        const isVideo = mediaType === 'video';

        photoSourceModeBlocks.forEach((node) => {
            node.hidden = isVideo;
        });

        if (sourceUploadContainer) {
            sourceUploadContainer.hidden = isVideo ? true : selectedSourceType() === 'url';
        }

        if (sourceUrlContainer) {
            sourceUrlContainer.hidden = isVideo ? true : selectedSourceType() !== 'url';
        }

        if (videoUploadContainer) {
            videoUploadContainer.hidden = !isVideo;
        }

        if (videoFramePicker) {
            videoFramePicker.hidden = !isVideo || !videoInput || !videoInput.files || !videoInput.files[0];
        }

        if (fileInput) {
            fileInput.required = !isVideo && selectedSourceType() !== 'url';
        }

        if (urlInput) {
            urlInput.required = !isVideo && selectedSourceType() === 'url';
        }

        if (videoInput) {
            videoInput.required = isVideo;
        }

        if (!isVideo) {
            clearVideoFrameData();
            setVideoFrameMessage('Загрузите видео, выберите момент и нажмите кнопку.');
        }
    };

    const updateSourceMode = () => {
        if (selectedMediaType() !== 'photo') {
            updateMediaMode();
            return;
        }

        const mode = selectedSourceType();
        const isUrl = mode === 'url';

        if (sourceUploadContainer) {
            sourceUploadContainer.hidden = isUrl;
        }

        if (sourceUrlContainer) {
            sourceUrlContainer.hidden = !isUrl;
        }

        if (fileInput) {
            fileInput.required = !isUrl;
        }

        if (urlInput) {
            urlInput.required = isUrl;
        }
    };

    const updatePreviewPosition = () => {
        const x = Number(focusXInput?.value || 50);
        const y = Number(focusYInput?.value || 50);

        if (previewImage) {
            previewImage.style.objectPosition = `${x}% ${y}%`;
        }

        if (focusXValue) {
            focusXValue.textContent = String(x);
        }

        if (focusYValue) {
            focusYValue.textContent = String(y);
        }
    };

    const loadPreviewFromImageFile = () => {
        if (!fileInput || !previewImage || !fileInput.files || !fileInput.files[0]) {
            return;
        }

        previewImage.src = URL.createObjectURL(fileInput.files[0]);
    };

    const loadPreviewFromUrl = () => {
        if (!urlInput || !previewImage) {
            return;
        }

        previewImage.src = urlInput.value.trim();
    };

    mediaTypeInputs.forEach((input) => input.addEventListener('change', updateMediaMode));
    sourceInputs.forEach((input) => input.addEventListener('change', updateSourceMode));

    if (focusXInput) {
        focusXInput.addEventListener('input', updatePreviewPosition);
    }

    if (focusYInput) {
        focusYInput.addEventListener('input', updatePreviewPosition);
    }

    if (fileInput) {
        fileInput.addEventListener('change', loadPreviewFromImageFile);
    }

    if (videoInput) {
        videoInput.addEventListener('change', prepareVideoFramePicker);
    }

    if (urlInput) {
        urlInput.addEventListener('input', loadPreviewFromUrl);
    }

    if (videoFramePlayer && videoFrameSeek && videoFrameTimeValue) {
        videoFramePlayer.addEventListener('loadedmetadata', () => {
            const duration = Number(videoFramePlayer.duration || 0);
            if (!Number.isFinite(duration) || duration <= 0) {
                videoFrameSeek.disabled = true;
                return;
            }

            const maxValue = Math.max(1, Math.floor(duration * 10));
            videoFrameSeek.max = String(maxValue);
            videoFrameSeek.value = '0';
            videoFrameSeek.disabled = false;
            videoFrameTimeValue.textContent = '0.0';
        });

        videoFrameSeek.addEventListener('input', () => {
            if (videoFrameSeek.disabled) {
                return;
            }

            const seconds = Number(videoFrameSeek.value || 0) / 10;
            videoFrameTimeValue.textContent = formatSeconds(seconds);

            if (Number.isFinite(seconds)) {
                try {
                    videoFramePlayer.currentTime = seconds;
                } catch (error) {
                    // ignore seek errors while metadata is still settling
                }
            }
        });
    }

    if (captureVideoFrameBtn && videoFramePlayer && videoPreviewFrameInput) {
        captureVideoFrameBtn.addEventListener('click', () => {
            if (videoFramePlayer.readyState < 2 || !videoFramePlayer.videoWidth || !videoFramePlayer.videoHeight) {
                setVideoFrameMessage('Видео еще не готово. Подождите секунду и попробуйте снова.');
                return;
            }

            const maxPreviewWidth = 640;
            const scale = Math.min(1, maxPreviewWidth / videoFramePlayer.videoWidth);
            const targetWidth = Math.max(1, Math.round(videoFramePlayer.videoWidth * scale));
            const targetHeight = Math.max(1, Math.round(videoFramePlayer.videoHeight * scale));

            const canvas = document.createElement('canvas');
            canvas.width = targetWidth;
            canvas.height = targetHeight;

            const context = canvas.getContext('2d');
            if (!context) {
                setVideoFrameMessage('Не удалось создать кадр. Попробуйте другой браузер.');
                return;
            }

            context.drawImage(videoFramePlayer, 0, 0, canvas.width, canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.6);

            if (dataUrl.length > 5 * 1024 * 1024) {
                setVideoFrameMessage('Кадр получился слишком тяжелым. Выберите другой момент или видео ниже качеством.');
                return;
            }

            videoPreviewFrameInput.value = dataUrl;

            if (previewImage) {
                previewImage.src = dataUrl;
            }

            setVideoFrameMessage(`Кадр выбран (${formatSeconds(videoFramePlayer.currentTime)}s).`);
        });
    }

    photoAddForm.addEventListener('submit', (event) => {
        if (selectedMediaType() !== 'video') {
            return;
        }

        const hasFrame = videoPreviewFrameInput && videoPreviewFrameInput.value.trim() !== '';
        if (!hasFrame) {
            event.preventDefault();
            setVideoFrameMessage('Сначала выберите кадр из видео для превью карточки.');
            if (videoFramePicker) {
                videoFramePicker.hidden = false;
            }
        }
    });

    updateMediaMode();
    updateSourceMode();
    updatePreviewPosition();
}

if (liveSearchForm && searchResultsContainer && searchDataNode) {
    let debounceTimer = null;
    const baseSearchPath = '/search.php';

    const qInput = liveSearchForm.querySelector('input[name="q"]');
    const tagInput = liveSearchForm.querySelector('input[name="tag"]');
    const dateFromInput = liveSearchForm.querySelector('input[name="date_from"]');
    const dateToInput = liveSearchForm.querySelector('input[name="date_to"]');

    const normalize = (value) => String(value || '').trim().toLowerCase();

    const normalizeQueryText = (value) => {
        const raw = String(value || '').trim();
        const cleaned = raw.replace(/\?q=.*/gi, '').trim();
        return normalize(cleaned);
    };

    const escapeHtml = (value) =>
        String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

    let allPhotos = [];

    if (toggleFiltersBtn && filtersDropdown) {
        filtersDropdown.classList.add('is-collapsed');
        filtersDropdown.classList.remove('is-open');
        toggleFiltersBtn.setAttribute('aria-expanded', 'false');

        toggleFiltersBtn.addEventListener('click', () => {
            const isExpanded = toggleFiltersBtn.getAttribute('aria-expanded') === 'true';
            const next = !isExpanded;

            toggleFiltersBtn.setAttribute('aria-expanded', next ? 'true' : 'false');
            filtersDropdown.classList.toggle('is-open', next);
            filtersDropdown.classList.toggle('is-collapsed', !next);
        });
    }

    try {
        const parsed = JSON.parse(searchDataNode.textContent || '[]');
        if (Array.isArray(parsed)) {
            allPhotos = parsed;
        }
    } catch (_error) {
        allPhotos = [];
    }

    const renderResults = (photos) => {
        if (!photos.length) {
            searchResultsContainer.innerHTML = '<div class="empty-state">Ничего не найдено по текущим параметрам.</div>';
            if (searchResultsCount) {
                searchResultsCount.textContent = '0 элементов';
            }
            return;
        }

        const cardsHtml = photos
            .map((photo) => {
                const tags = Array.isArray(photo.tags) ? photo.tags : [];
                const tagsHtml = tags.length
                    ? `<div class="tag-list">${tags.map((tag) => `<span class="tag-chip">#${escapeHtml(tag)}</span>`).join('')}</div>`
                    : '';

                const focusX = Number(photo.focusX ?? 50);
                const focusY = Number(photo.focusY ?? 50);
                const mediaLabel = String(photo.mediaType || 'photo') === 'video' ? 'Видео' : 'Фото';

                return `<article class="photo-card">
                    <a class="card-image-wrap" href="/photo.php?id=${Number(photo.id || 0)}">
                        <img src="${escapeHtml(photo.imageUrl || '')}" alt="${escapeHtml(photo.title || '')}" loading="lazy" style="object-position: ${focusX}% ${focusY}%;">
                    </a>
                    <div class="card-content">
                        <h3>${escapeHtml(photo.title || '')}</h3>
                        <p>${escapeHtml(photo.description || '')}</p>
                        ${tagsHtml}
                        <p class="media-type-label">${escapeHtml(mediaLabel)}</p>
                        <time>${escapeHtml(photo.displayDate || '')}</time>
                    </div>
                </article>`;
            })
            .join('');

        searchResultsContainer.innerHTML = `<div class="gallery-grid">${cardsHtml}</div>`;
        if (searchResultsCount) {
            searchResultsCount.textContent = `${photos.length} элементов`;
        }
    };

    const updateUrl = (qValue, tagValue, fromValue, toValue) => {
        const params = new URLSearchParams();

        if (qValue) {
            params.set('q', qValue);
        }

        if (tagValue) {
            params.set('tag', tagValue);
        }

        if (fromValue) {
            params.set('date_from', fromValue);
        }

        if (toValue) {
            params.set('date_to', toValue);
        }

        const nextUrl = `${baseSearchPath}${params.toString() ? `?${params.toString()}` : ''}`;
        window.history.replaceState({}, '', nextUrl);
    };

    const runSearch = () => {
        const qValue = normalizeQueryText(qInput?.value);

        if (qInput && qInput.value !== qValue) {
            qInput.value = qValue;
        }

        const tagValue = normalize(tagInput?.value);
        const fromValue = (dateFromInput?.value || '').trim();
        const toValue = (dateToInput?.value || '').trim();

        if (fromValue && toValue && fromValue > toValue) {
            if (searchHint) {
                searchHint.textContent = 'Дата "от" не может быть больше даты "до".';
            }
            return;
        }

        const titleTokens = qValue ? qValue.split(/\s+/).filter((token) => token) : [];

        const filtered = allPhotos.filter((photo) => {
            const tags = Array.isArray(photo.tags) ? photo.tags : [];
            const haystack = normalize(`${photo.title || ''} ${photo.description || ''} ${tags.join(' ')}`);

            for (const token of titleTokens) {
                if (!haystack.includes(token)) {
                    return false;
                }
            }

            if (tagValue) {
                const tagTokens = tagValue.split(/[\s,;]+/).filter((token) => token !== '');

                if (tagTokens.length > 0) {
                    const hasAnyToken = tagTokens.some((token) => tags.some((tag) => normalize(tag).includes(token)));
                    if (!hasAnyToken) {
                        return false;
                    }
                }
            }

            const photoDate = String(photo.displayDate || '');
            if (fromValue && photoDate < fromValue) {
                return false;
            }

            if (toValue && photoDate > toValue) {
                return false;
            }

            return true;
        });

        if (searchHint) {
            if (qValue === '' && tagValue === '' && fromValue === '' && toValue === '') {
                searchHint.textContent = 'Пустой поиск: показаны все фото.';
            } else {
                searchHint.textContent = 'Результаты обновляются в реальном времени.';
            }
        }

        renderResults(filtered);
        updateUrl(qValue, tagValue, fromValue, toValue);
    };

    const scheduleSearch = () => {
        if (debounceTimer) {
            window.clearTimeout(debounceTimer);
        }

        debounceTimer = window.setTimeout(runSearch, 180);
    };

    liveSearchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        runSearch();
    });

    if (qInput) {
        qInput.addEventListener('input', scheduleSearch);
        qInput.addEventListener('search', scheduleSearch);
        qInput.addEventListener('change', runSearch);
    }

    [tagInput, dateFromInput, dateToInput].forEach((field) => {
        if (!field) {
            return;
        }

        field.addEventListener('input', scheduleSearch);
        field.addEventListener('change', runSearch);
    });

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (tagInput) {
                tagInput.value = '';
            }

            if (dateFromInput) {
                dateFromInput.value = '';
            }

            if (dateToInput) {
                dateToInput.value = '';
            }

            runSearch();
        });
    }

    runSearch();
}

