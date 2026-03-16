@props([
    'placeholder' => 'Type a command or search...',
])

<div
    {{ $attributes->merge(['class' => '']) }}
    x-data="{
        open: false,
        search: '',
        activeIndex: 0,
        memberResults: [],
        badgeColors: { 'organisation': 's-badge-blue', 'venue': 's-badge-amber', 'contact': 's-badge-green' },
        avatarColors: { 'organisation': 's-avatar-blue', 'venue': 's-avatar-amber', 'contact': 's-avatar-green' },
        searching: false,
        debounceTimer: null,
        commands: [
            { group: 'Navigation', label: 'Dashboard', icon: 'home', url: '{{ route('dashboard') }}', keywords: 'home main overview' },
            { group: 'Navigation', label: 'Members', icon: 'users', url: '{{ route('members.index') }}', keywords: 'contacts organisations venues people' },
            { group: 'Navigation', label: 'Admin Settings', icon: 'cog', url: '{{ route('admin.settings.company') }}', keywords: 'setup configuration company' },
            { group: 'Create', label: 'New Member', icon: 'plus', url: '{{ route('members.create') }}', keywords: 'add create organisation contact venue' },
            { group: 'Settings', label: 'Profile Settings', icon: 'user', url: '{{ route('settings.profile') }}', keywords: 'account name email' },
            { group: 'Settings', label: 'Change Password', icon: 'lock', url: '{{ route('settings.password') }}', keywords: 'security password' },
            { group: 'Settings', label: 'Appearance', icon: 'palette', url: '{{ route('settings.appearance') }}', keywords: 'theme dark light mode' },
            { group: 'Admin', label: 'Users & Security', icon: 'shield', url: '{{ route('admin.settings.users') }}', keywords: 'users roles permissions security' },
            { group: 'Admin', label: 'Roles & Permissions', icon: 'key', url: '{{ route('admin.settings.roles') }}', keywords: 'roles permissions access' },
            { group: 'Admin', label: 'Custom Fields', icon: 'grid', url: '{{ route('admin.settings.custom-fields') }}', keywords: 'fields custom metadata' },
            { group: 'Admin', label: 'List Values', icon: 'list', url: '{{ route('admin.settings.list-names') }}', keywords: 'lists values types categories' },
            { group: 'Admin', label: 'Tax Rates', icon: 'calculator', url: '{{ route('admin.settings.tax.rates') }}', keywords: 'tax vat rates' },
            { group: 'Admin', label: 'Email Settings', icon: 'mail', url: '{{ route('admin.settings.email') }}', keywords: 'smtp email sending' },
            { group: 'Admin', label: 'Email Templates', icon: 'template', url: '{{ route('admin.settings.email-templates') }}', keywords: 'templates email notifications' },
            { group: 'Admin', label: 'Notifications', icon: 'bell', url: '{{ route('admin.settings.notifications') }}', keywords: 'notifications channels alerts' },
            { group: 'Admin', label: 'Webhooks', icon: 'bolt', url: '{{ route('admin.settings.webhooks') }}', keywords: 'webhooks api integrations' },
            { group: 'Admin', label: 'Action Log', icon: 'clipboard', url: '{{ route('admin.settings.action-log') }}', keywords: 'audit log history actions' },
            { group: 'Admin', label: 'System Health', icon: 'heart', url: '{{ route('admin.settings.system-health') }}', keywords: 'health status monitoring' },
            { group: 'Admin', label: 'Countries', icon: 'globe', url: '{{ route('admin.settings.countries') }}', keywords: 'countries regions' },
            { group: 'Admin', label: 'Stores', icon: 'building', url: '{{ route('admin.settings.stores') }}', keywords: 'stores locations warehouses' },
            { group: 'Admin', label: 'Branding', icon: 'palette', url: '{{ route('admin.settings.branding') }}', keywords: 'logo brand colors' },
            { group: 'Admin', label: 'API Tokens', icon: 'key', url: '{{ route('admin.settings.api') }}', keywords: 'api tokens keys access' },
        ],
        get filteredCommands() {
            if (!this.search) return this.commands;
            const q = this.search.toLowerCase();
            return this.commands.filter(c =>
                c.label.toLowerCase().includes(q) ||
                c.group.toLowerCase().includes(q) ||
                (c.keywords && c.keywords.toLowerCase().includes(q))
            );
        },
        get allResults() {
            const results = [];
            this.memberResults.forEach(m => results.push({ group: 'Members', label: m.name, hint: m.type, icon: 'users', url: m.url, member: m }));
            this.filteredCommands.forEach(c => results.push(c));
            return results;
        },
        get grouped() {
            const groups = {};
            this.allResults.forEach(c => {
                if (!groups[c.group]) groups[c.group] = [];
                groups[c.group].push(c);
            });
            return groups;
        },
        searchMembers() {
            clearTimeout(this.debounceTimer);
            if (this.search.length < 2) {
                this.memberResults = [];
                return;
            }
            this.searching = true;
            this.debounceTimer = setTimeout(() => {
                fetch('{{ route('search') }}?q=' + encodeURIComponent(this.search), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => { this.memberResults = data.members || []; this.searching = false; })
                .catch(() => { this.memberResults = []; this.searching = false; });
            }, 200);
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.search = '';
                this.activeIndex = 0;
                this.memberResults = [];
                this.$nextTick(() => this.$refs.searchInput?.focus());
            }
        },
        navigate(url) {
            this.open = false;
            Livewire.navigate(url);
        },
        onKeydown(e) {
            const items = this.allResults;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1); this.scrollToActive(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.activeIndex = Math.max(this.activeIndex - 1, 0); this.scrollToActive(); }
            else if (e.key === 'Enter' && items[this.activeIndex]) { e.preventDefault(); this.navigate(items[this.activeIndex].url); }
        },
        scrollToActive() {
            this.$nextTick(() => {
                const el = this.$refs.results?.querySelector('[data-active=true]');
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.toggle();
                }
                if (e.key === '/' && !this.open && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                    e.preventDefault();
                    this.toggle();
                }
            });
        },
    }"
    x-on:keydown.escape.window="open = false"
>
    <template x-teleport="body">
        <div class="s-command-backdrop" x-show="open" x-cloak x-transition.opacity x-on:click.self="open = false">
            <div class="s-command-palette" x-trap.noscroll="open" x-on:keydown="onKeydown($event)">
                <div class="s-command-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input
                        x-ref="searchInput"
                        type="text"
                        placeholder="{{ $placeholder }}"
                        x-model="search"
                        x-on:input="activeIndex = 0; searchMembers()"
                    >
                    <button class="s-command-close" x-on:click="open = false" type="button">
                        <span class="s-kbd">Esc</span>
                    </button>
                </div>
                <div class="s-command-results" x-ref="results">
                    <template x-if="allResults.length > 0">
                        <div>
                            <template x-for="(group, groupName) in grouped" :key="groupName">
                                <div>
                                    <div class="s-command-group" x-text="groupName"></div>
                                    <template x-for="cmd in group" :key="cmd.label + cmd.url">
                                        <button
                                            class="s-command-item"
                                            :class="{ 'active': allResults[activeIndex] && allResults[activeIndex].label === cmd.label && allResults[activeIndex].url === cmd.url }"
                                            :data-active="allResults[activeIndex] && allResults[activeIndex].label === cmd.label && allResults[activeIndex].url === cmd.url"
                                            x-on:click="navigate(cmd.url)"
                                            x-on:mouseenter="activeIndex = allResults.findIndex(r => r.label === cmd.label && r.url === cmd.url)"
                                            type="button"
                                        >
                                            {{-- Member avatar (icon-based, same as /members datatable) --}}
                                            <template x-if="cmd.member">
                                                <span style="display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--s-subtle); flex-shrink: 0;">
                                                    <template x-if="cmd.member.typeValue === 'organisation'"><svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" style="width: 14px; height: 14px;"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg></template>
                                                    <template x-if="cmd.member.typeValue === 'venue'"><svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" style="width: 14px; height: 14px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></template>
                                                    <template x-if="cmd.member.typeValue === 'contact'"><svg viewBox="0 0 24 24" fill="none" stroke="var(--text-secondary)" stroke-width="1.5" style="width: 14px; height: 14px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></template>
                                                </span>
                                            </template>
                                            {{-- Command icon --}}
                                            <template x-if="!cmd.member">
                                            <span class="s-command-item-icon">
                                                <template x-if="cmd.icon === 'home'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></template>
                                                <template x-if="cmd.icon === 'users'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></template>
                                                <template x-if="cmd.icon === 'plus'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></template>
                                                <template x-if="cmd.icon === 'cog'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></template>
                                                <template x-if="cmd.icon === 'user'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></template>
                                                <template x-if="cmd.icon === 'lock'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></template>
                                                <template x-if="cmd.icon === 'palette'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="8" r="1.5" fill="currentColor"/><circle cx="8" cy="12" r="1.5" fill="currentColor"/><circle cx="16" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="16" r="1.5" fill="currentColor"/></svg></template>
                                                <template x-if="cmd.icon === 'shield'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></template>
                                                <template x-if="cmd.icon === 'key'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></template>
                                                <template x-if="cmd.icon === 'grid'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></template>
                                                <template x-if="cmd.icon === 'list'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></template>
                                                <template x-if="cmd.icon === 'calculator'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="10" y2="10"/><line x1="14" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="10" y2="14"/><line x1="14" y1="14" x2="16" y2="14"/></svg></template>
                                                <template x-if="cmd.icon === 'mail'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></template>
                                                <template x-if="cmd.icon === 'template'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg></template>
                                                <template x-if="cmd.icon === 'bell'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg></template>
                                                <template x-if="cmd.icon === 'bolt'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></template>
                                                <template x-if="cmd.icon === 'clipboard'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></template>
                                                <template x-if="cmd.icon === 'heart'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></template>
                                                <template x-if="cmd.icon === 'globe'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg></template>
                                                <template x-if="cmd.icon === 'building'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg></template>
                                            </span>
                                            </template>
                                            <span class="s-command-item-label" x-text="cmd.label"></span>
                                            {{-- Member badges --}}
                                            <template x-if="cmd.member">
                                                <span style="display: inline-flex; align-items: center; gap: 4px;">
                                                    <span class="s-badge" :class="badgeColors[cmd.member.typeValue] || 's-badge-zinc'" style="display: inline-flex; align-items: center; gap: 3px;">
                                                        <template x-if="cmd.member.typeValue === 'organisation'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 10px; height: 10px;"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M14 8h1"/><path d="M14 12h1"/></svg></template>
                                                        <template x-if="cmd.member.typeValue === 'venue'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 10px; height: 10px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></template>
                                                        <template x-if="cmd.member.typeValue === 'contact'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 10px; height: 10px;"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg></template>
                                                        <span x-text="cmd.member.type"></span>
                                                    </span>
                                                    <template x-if="cmd.member.isActive">
                                                        <span class="s-badge s-badge-green"><span class="s-badge-dot"></span> Active</span>
                                                    </template>
                                                    <template x-if="!cmd.member.isActive">
                                                        <span class="s-badge s-badge-zinc"><span class="s-badge-dot"></span> Inactive</span>
                                                    </template>
                                                </span>
                                            </template>
                                            <template x-if="cmd.hint && !cmd.member">
                                                <span class="s-command-item-hint" x-text="cmd.hint"></span>
                                            </template>
                                        </button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="searching">
                        <div class="s-command-empty">
                            <div class="s-spinner" style="width: 20px; height: 20px; margin-bottom: 8px;"></div>
                            <span>Searching...</span>
                        </div>
                    </template>
                    <template x-if="allResults.length === 0 && !searching && search.length >= 2">
                        <div class="s-command-empty">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 32px; height: 32px; opacity: 0.3; margin-bottom: 8px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <span>No results for "<span x-text="search"></span>"</span>
                        </div>
                    </template>
                </div>
                <div class="s-command-footer">
                    <span><span class="s-kbd">/</span> Open</span>
                    <span><span class="s-kbd">↑↓</span> Navigate</span>
                    <span><span class="s-kbd">↵</span> Go</span>
                    <span><span class="s-kbd">Esc</span> Close</span>
                </div>
            </div>
        </div>
    </template>
</div>
