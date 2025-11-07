/**
 * Error Boundary Components for V3 Blocks
 * Handles errors during block preview rendering, particularly from invalid HTML
 */

import { Component, createContext } from '@wordpress/element';
import { BlockPlaceholder } from './block-placeholder';

// Error Boundary Context
const ErrorBoundaryContext = createContext( null );

// Error Boundary Component
export class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.resetErrorBoundary = this.resetErrorBoundary.bind( this );
		this.state = { didCatch: false, error: null };
	}

	static getDerivedStateFromError( error ) {
		return { didCatch: true, error: error };
	}

	resetErrorBoundary() {
		const { error } = this.state;
		if ( error !== null ) {
			this.setState( { didCatch: false, error: null } );
		}
	}

	componentDidCatch( error, errorInfo ) {
		acf.debug( 'Block preview error caught:', error, errorInfo );
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

// Fallback component to show when preview errors
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
