import { Component, createContext } from '@wordpress/element';
import { BlockPlaceholder } from './block-placeholder';

// Create context outside the class
export const ErrorBoundaryContext = createContext( null );

// Initial state constant
const initialState = { didCatch: false, error: null };

// Error Boundary Component
export class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.resetErrorBoundary = this.resetErrorBoundary.bind( this );
		this.state = initialState;
	}

	static getDerivedStateFromError( error ) {
		return { didCatch: true, error: error };
	}

	resetErrorBoundary() {
		const { error } = this.state;
		if ( error !== null ) {
			// Collect all arguments passed to reset
			const args = Array.from( arguments );

			// Call optional onReset callback with context
			if ( this.props.onReset ) {
				this.props.onReset( {
					args: args,
					reason: 'imperative-api',
				} );
			}

			this.setState( initialState );
		}
	}

	componentDidCatch( error, errorInfo ) {
		acf.debug( 'Block preview error caught:', error, errorInfo );

		// Call optional onError callback
		if ( this.props.onError ) {
			this.props.onError( error, errorInfo );
		}
	}

	componentDidUpdate( prevProps, prevState ) {
		const { didCatch } = this.state;
		const { resetKeys } = this.props;

		// Auto-reset if resetKeys prop changed
		if (
			didCatch &&
			prevState.error !== null &&
			hasResetKeysChanged( prevProps.resetKeys, resetKeys )
		) {
			if ( this.props.onReset ) {
				this.props.onReset( {
					next: resetKeys,
					prev: prevProps.resetKeys,
					reason: 'keys',
				} );
			}
			this.setState( initialState );
		}
	}

	render() {
		const { children, fallbackRender, FallbackComponent, fallback } =
			this.props;
		const { didCatch, error } = this.state;

		let content = children;

		if ( didCatch ) {
			const errorProps = {
				error: error,
				resetErrorBoundary: this.resetErrorBoundary,
			};

			if ( typeof fallbackRender === 'function' ) {
				content = fallbackRender( errorProps );
			} else if ( FallbackComponent ) {
				content = <FallbackComponent { ...errorProps } />;
			} else if ( fallback !== undefined ) {
				content = fallback;
			} else {
				throw error;
			}
		}

		return (
			<ErrorBoundaryContext.Provider
				value={ {
					didCatch,
					error,
					resetErrorBoundary: this.resetErrorBoundary,
				} }
			>
				{ content }
			</ErrorBoundaryContext.Provider>
		);
	}
}

// Helper function to check if reset keys changed
function hasResetKeysChanged( prevKeys = [], nextKeys = [] ) {
	return (
		prevKeys.length !== nextKeys.length ||
		prevKeys.some( ( key, index ) => ! Object.is( key, nextKeys[ index ] ) )
	);
}

export const BlockPreviewErrorFallback = ( {
	setBlockFormModalOpen,
	blockLabel,
	error,
} ) => {
	let errorMessage = null;

	if ( error ) {
		acf.debug( 'Block preview error:', error );
		errorMessage = acf.__( 'Error previewing block v3' );
	}

	return (
		<BlockPlaceholder
			setBlockFormModalOpen={ setBlockFormModalOpen }
			blockLabel={ blockLabel }
			instructions={ errorMessage }
		/>
	);
};
