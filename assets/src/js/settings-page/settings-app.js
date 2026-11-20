/**
 * The settings page app: tab bar, the active tab and snackbar notices.
 *
 * Tabs switch in-page: the app loads the tab's data with the "acf_settings_tab_data" AJAX action and updates
 * the URL, falling back to a full page load if that fails.
 *
 * @since SCF 6.9.10
 */

import {
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { SnackbarList, TabPanel } from '@wordpress/components';

import SettingsPage from './components/settings-page';
import CustomTabPage from './components/custom-tab-page';
import { getTab, subscribe } from './settings-tab-registry';
import { postAjax } from './utils';

/**
 * Loads a tab's data.
 *
 * @param {Object} config The current config.
 * @param {string} tabKey The tab to load.
 * @return {Promise<Object|null>} The tab's config changes, or null if it couldn't be loaded.
 */
async function fetchTabData( config, tabKey ) {
	const { payload } = await postAjax( config.ajaxUrl, {
		action: 'acf_settings_tab_data',
		nonce: config.nonce,
		tab: tabKey,
	} );

	return payload?.success && payload.data ? payload.data : null;
}

/**
 * Renders the settings app.
 *
 * @param {Object} props               Props.
 * @param {Object} props.initialConfig The localized acfSettingsPage data.
 */
export default function SettingsApp( { initialConfig } ) {
	const [ config, setConfig ] = useState( initialConfig );
	const [ notices, setNotices ] = useState( [] );
	// Puts the tab bar back on the active tab after a cancelled or history-driven switch.
	const [ tabBarSync, syncTabBar ] = useReducer( ( count ) => count + 1, 0 );
	const configRef = useRef( config );
	configRef.current = config;
	// Incremented on every tab switch so a slow response for an earlier switch can't overwrite a later one.
	const switchRequestRef = useRef( 0 );
	const hasUnsavedChangesRef = useRef( false );
	const tabBarRef = useRef( null );

	const strings = config.strings || {};
	const tabs = useMemo(
		() => ( Array.isArray( config.tabs ) ? config.tabs : [] ),
		[ config.tabs ]
	);

	const addNotice = useCallback( ( content, status = 'success' ) => {
		setNotices( ( current ) => [
			...current,
			{
				id: `${ Date.now() }-${ Math.random() }`,
				content,
				status,
			},
		] );
	}, [] );

	const onUnsavedChangesChange = useCallback( ( hasUnsavedChanges ) => {
		hasUnsavedChangesRef.current = hasUnsavedChanges;
	}, [] );

	// Returns true if there are no unsaved changes, or the user confirms discarding them.
	const confirmDiscardChanges = () => {
		if ( ! hasUnsavedChangesRef.current ) {
			return true;
		}
		// A native confirm matches the browser's own beforeunload prompt for a full page load.
		const message = configRef.current.strings?.unsavedChanges ?? '';
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( message ) ) {
			return false;
		}
		// Clear the flag so the beforeunload guard doesn't prompt again if we fall back to a full page load.
		hasUnsavedChangesRef.current = false;
		return true;
	};

	useEffect( () => {
		const handleBeforeUnload = ( event ) => {
			if ( hasUnsavedChangesRef.current ) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handleBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', handleBeforeUnload );
	}, [] );

	// The server renders the loaded tab's label at the start of the title, so swap it for the active tab's after an in-page switch.
	const titleLabelRef = useRef(
		tabs.find( ( tab ) => tab.key === config.activeTab )?.label ?? ''
	);
	useEffect( () => {
		const label = tabs.find(
			( tab ) => tab.key === config.activeTab
		)?.label;
		if ( ! label || label === titleLabelRef.current ) {
			return;
		}
		if (
			titleLabelRef.current &&
			document.title.startsWith( titleLabelRef.current )
		) {
			document.title =
				label + document.title.slice( titleLabelRef.current.length );
			titleLabelRef.current = label;
		}
	}, [ config.activeTab, tabs ] );

	// The tab bar keeps its own selection, so select the active tab in it when they differ. Remounting it
	// instead would also remount the tab's content and lose its unsaved changes.
	useEffect( () => {
		const index = tabs.findIndex( ( tab ) => tab.key === config.activeTab );
		// Only the app's own tab bar, not one a custom tab renders.
		const button = tabBarRef.current?.querySelectorAll(
			':scope > .scf-settings-tabs > .components-tab-panel__tabs > .components-tab-panel__tabs-item'
		)[ index ];
		if ( button && ! button.classList.contains( 'is-active' ) ) {
			// onSelectTab() ignores the active tab, so this only updates the tab bar.
			button.click();
		}
	}, [ config.activeTab, tabs, tabBarSync ] );

	const switchTab = async ( tabKey, { pushState = true } = {} ) => {
		const current = configRef.current;
		const targetTab = current.tabs.find( ( tab ) => tab.key === tabKey );
		if ( ! targetTab ) {
			return;
		}

		const requestId = ++switchRequestRef.current;
		try {
			const data = await fetchTabData( current, tabKey );
			// A later tab switch has started, so let that one win.
			if ( requestId !== switchRequestRef.current ) {
				return;
			}
			if ( data ) {
				setConfig( ( prev ) => ( {
					...prev,
					...data,
					activeTab: tabKey,
				} ) );
				if ( pushState ) {
					window.history.pushState(
						{ acfTab: tabKey },
						'',
						targetTab.url
					);
				}
				window.scrollTo( 0, 0 );
				return;
			}
		} catch {
			if ( requestId !== switchRequestRef.current ) {
				return;
			}
			// Fall through to a full page load.
		}

		if ( pushState ) {
			window.location.href = targetTab.url;
		} else {
			window.location.reload();
		}
	};

	const onSelectTab = ( tabKey ) => {
		const current = configRef.current;
		if ( tabKey === current.activeTab ) {
			return;
		}

		if ( ! confirmDiscardChanges() ) {
			// Put the tab bar back on the active tab.
			syncTabBar();
			return;
		}

		switchTab( tabKey );
	};

	useEffect( () => {
		const handlePopState = () => {
			const current = configRef.current;
			const targetPage =
				new window.URLSearchParams( window.location.search ).get(
					'page'
				) ?? '';
			// Match on the page slug in each tab's URL so the slug format is only defined in PHP.
			const targetTab = current.tabs.find(
				( tab ) =>
					new window.URL(
						tab.url,
						window.location.href
					).searchParams.get( 'page' ) === targetPage
			);

			if ( ! targetTab ) {
				return;
			}

			// Navigating back to the rendered tab (e.g. Back then Forward mid-switch) must still cancel a pending switch.
			if ( targetTab.key === current.activeTab ) {
				++switchRequestRef.current;
				return;
			}

			// The browser has already changed the URL, so put it back if the user stays.
			if ( ! confirmDiscardChanges() ) {
				const currentTab = current.tabs.find(
					( tab ) => tab.key === current.activeTab
				);
				if ( currentTab ) {
					window.history.pushState(
						{ acfTab: currentTab.key },
						'',
						currentTab.url
					);
				}
				return;
			}

			switchTab( targetTab.key, { pushState: false } );
		};

		window.addEventListener( 'popstate', handlePopState );
		return () => window.removeEventListener( 'popstate', handlePopState );
	}, [] );

	// Re-render when a tab script registers, in case it loads after the app has mounted.
	const [ , onTabRegistered ] = useReducer( ( count ) => count + 1, 0 );
	useEffect( () => subscribe( onTabRegistered ), [] );

	// Tabs registered with acf.settingsPage.registerTab() render their own UI inside SCF's layout.
	const customTab = getTab( config.activeTab );
	const content = customTab ? (
		<CustomTabPage
			key={ config.activeTab }
			tab={ customTab }
			config={ config }
		/>
	) : (
		<SettingsPage
			key={ config.activeTab }
			config={ config }
			addNotice={ addNotice }
			onUnsavedChangesChange={ onUnsavedChangesChange }
		/>
	);

	return (
		<div className="scf-settings" ref={ tabBarRef }>
			{ tabs.length > 0 ? (
				<TabPanel
					className="scf-settings-tabs"
					aria-label={ strings.settingsTabs }
					tabs={ tabs.map( ( tab ) => ( {
						name: tab.key,
						title: tab.count ? (
							<>
								{ tab.label }
								<span className="scf-settings-tab-count">
									{ tab.count }
								</span>
							</>
						) : (
							tab.label
						),
					} ) ) }
					initialTabName={ config.activeTab }
					selectOnMove={ false }
					onSelect={ onSelectTab }
				>
					{ () => (
						<div className="scf-settings-tab-content">
							{ content }
						</div>
					) }
				</TabPanel>
			) : (
				<div className="scf-settings-tab-content">{ content }</div>
			) }
			<SnackbarList
				className="scf-settings-snackbars"
				notices={ notices }
				onRemove={ ( id ) =>
					setNotices( ( current ) =>
						current.filter( ( notice ) => notice.id !== id )
					)
				}
			/>
		</div>
	);
}
