document.addEventListener('DOMContentLoaded', () => {
    // Intercept clicks on anchor tags
    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a');
        
        // Prevent intercepting external links, new tabs, or modifier keys
        if (!link || 
            link.target === '_blank' || 
            link.hostname !== window.location.hostname ||
            link.hasAttribute('download') ||
            link.getAttribute('href').startsWith('#') ||
            e.ctrlKey || e.metaKey) {
            return;
        }
        
        const url = link.href;
        const mainArea = document.querySelector('.provider-content-area');
        
        // If content area doesn't exist, let browser handle normal navigation
        if (!mainArea) return;
        
        e.preventDefault();
        
        // Elegant loading state: Top progress bar + subtle blur
        let loader = document.getElementById('spa-top-loader');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'spa-top-loader';
            loader.className = 'spa-top-loader';
            document.body.appendChild(loader);
        }
        
        loader.style.opacity = '1';
        loader.style.background = 'var(--accent, #ff6b4a)';
        loader.style.width = '20%';
        
        let progress = 20;
        const progressInterval = setInterval(() => {
            progress += (100 - progress) * 0.15;
            if (progress > 95) progress = 95;
            loader.style.width = `${progress}%`;
        }, 150);

        mainArea.style.transition = 'opacity 0.2s ease-in-out';
        mainArea.style.opacity = '0.7';
        
        try {
            // Fetch new page in background
            const response = await fetch(url, {
                headers: { 'X-SPA-Router': 'true' },
                cache: 'no-store'
            });
            
            const html = await response.text();
            
            // Parse new HTML Document
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newMain = doc.querySelector('.provider-content-area');
            
            if (newMain) {
                // Pre-load new stylesheets BEFORE swapping content to prevent FOUC
                // Cache-busting query strings change on every Laravel response.
                // Compare stylesheet paths so SPA navigation does not append the
                // same global CSS repeatedly in a different cascade order.
                const stylesheetKey = (href) => {
                    try {
                        return new URL(href, window.location.href).pathname;
                    } catch (error) {
                        return href;
                    }
                };
                const currentLinks = new Set(
                    Array.from(document.head.querySelectorAll('link[rel="stylesheet"]'))
                        .map(link => stylesheetKey(link.href))
                );
                const newLinks = Array.from(doc.head.querySelectorAll('link[rel="stylesheet"]'));
                const cssPromises = [];
                
                newLinks.forEach(newLink => {
                    const linkKey = stylesheetKey(newLink.href);
                    if (!currentLinks.has(linkKey)) {
                        currentLinks.add(linkKey);
                        const promise = new Promise((resolve) => {
                            const linkNode = document.createElement('link');
                            linkNode.rel = 'stylesheet';
                            linkNode.href = newLink.href;
                            linkNode.onload = resolve;
                            linkNode.onerror = resolve; // Resolve anyway to not break navigation
                            document.head.appendChild(linkNode);
                        });
                        cssPromises.push(promise);
                    }
                });
                
                // Wait for all new CSS to load
                if (cssPromises.length > 0) {
                    await Promise.all(cssPromises);
                }

                // Swap ONLY the content
                mainArea.innerHTML = newMain.innerHTML;
                
                // Manually re-evaluate scripts inside the injected content
                const scripts = mainArea.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                document.dispatchEvent(new CustomEvent('provider:content-loaded', {
                    detail: { url }
                }));
                
                // Update History API and Page Title
                window.history.pushState({}, '', url);
                document.title = doc.title;
                
                const newSidebarNav = doc.querySelector('.sidebar-main-nav');
                const currentSidebarNav = document.querySelector('.sidebar-main-nav');
                const sidebarIndicator = document.getElementById('sidebarIndicator');
                
                if (newSidebarNav && currentSidebarNav) {
                    // Remove old anchor tags but keep the sliding indicator
                    Array.from(currentSidebarNav.children).forEach(child => {
                        if (child.id !== 'sidebarIndicator') {
                            child.remove();
                        }
                    });
                    
                    // Append new anchor tags
                    Array.from(newSidebarNav.querySelectorAll('a')).forEach(a => {
                        currentSidebarNav.appendChild(a.cloneNode(true));
                    });
                }
                
                // Update active state on sidebar menus & move liquid indicator
                let foundSidebarActive = false;
                document.querySelectorAll('.sidebar-main-nav a').forEach(a => {
                    a.classList.remove('active');
                    if (a.href === url || a.href === url.split('?')[0]) {
                        a.classList.add('active');
                        if (sidebarIndicator) {
                            sidebarIndicator.style.opacity = '1';
                            sidebarIndicator.style.top = a.offsetTop + 'px';
                        }
                        foundSidebarActive = true;
                    }
                });
                
                if (!foundSidebarActive && sidebarIndicator) {
                    sidebarIndicator.style.opacity = '0';
                }
                
                // Update active state on topbar pills from the fetched document
                const newActiveTopbar = doc.querySelector('.topbar-pills .pill-btn.active');
                const topbarIndicator = document.getElementById('topbarIndicator');
                
                document.querySelectorAll('.topbar-pills .pill-btn').forEach(a => {
                    a.classList.remove('active');
                });
                
                if (newActiveTopbar) {
                    // Match by href or text to find the corresponding current element
                    const newHref = newActiveTopbar.getAttribute('href');
                    const matchingCurrent = document.querySelector(`.topbar-pills .pill-btn[href="${newHref}"]`);
                    
                    if (matchingCurrent) {
                        matchingCurrent.classList.add('active');
                        if (topbarIndicator) {
                            topbarIndicator.style.width = matchingCurrent.offsetWidth + 'px';
                            topbarIndicator.style.left = matchingCurrent.offsetLeft + 'px';
                        }
                    } else if (topbarIndicator) {
                        topbarIndicator.style.width = '0px';
                    }
                } else if (topbarIndicator) {
                    topbarIndicator.style.width = '0px';
                }
                
                
                // Complete the loading bar
                clearInterval(progressInterval);
                loader.style.width = '100%';
                
                setTimeout(() => {
                    loader.style.opacity = '0';
                    setTimeout(() => loader.style.width = '0%', 300);
                }, 300);
                
            } else {
                // Fallback for pages without standard layout (e.g. login/errors)
                window.location.href = url;
            }
        } catch (err) {
            clearInterval(progressInterval);
            if (loader) {
                loader.style.width = '100%';
                loader.style.background = 'var(--danger, red)';
            }
            // Fallback on network error
            window.location.href = url;
        } finally {
            // Smooth fade in
            mainArea.style.opacity = '1';
            // A non-none filter (including blur(0)) creates a containing block
            // for position:fixed descendants. Clearing it keeps calendar modals
            // attached to the viewport, exactly like a full page refresh.
            mainArea.style.removeProperty('filter');
            window.setTimeout(() => {
                mainArea.style.removeProperty('opacity');
                mainArea.style.removeProperty('transition');
            }, 220);
        }
    });

    // Refresh page when hitting back button to ensure proper state
    window.addEventListener('popstate', () => {
        window.location.reload();
    });

    // Initialize sliding indicator positions on load
    const initSlidingIndicator = () => {
        const topbarIndicator = document.getElementById('topbarIndicator');
        const activeTopbar = document.querySelector('.topbar-pills .pill-btn.active');
        if (topbarIndicator) {
            if (activeTopbar) {
                topbarIndicator.style.width = activeTopbar.offsetWidth + 'px';
                topbarIndicator.style.left = activeTopbar.offsetLeft + 'px';
            } else {
                topbarIndicator.style.width = '0px';
            }
        }
        
        const sidebarIndicator = document.getElementById('sidebarIndicator');
        const activeSidebar = document.querySelector('.sidebar-main-nav a.active');
        if (sidebarIndicator) {
            if (activeSidebar) {
                sidebarIndicator.style.opacity = '1';
                sidebarIndicator.style.top = activeSidebar.offsetTop + 'px';
            } else {
                sidebarIndicator.style.opacity = '0';
            }
        }
    };
    // Small delay to ensure fonts/layout are calculated
    setTimeout(initSlidingIndicator, 100);
    // Also re-init on resize just in case layout shifts
    window.addEventListener('resize', initSlidingIndicator);
});
