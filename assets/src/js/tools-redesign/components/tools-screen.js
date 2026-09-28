/**
 * Tools Screen
 *
 * Top level component of the prototype. Lays the import and export tools
 * out as cards, replacing the metabox approach.
 *
 * @since SCF 6.5.0
 */

import { Flex, FlexItem } from '@wordpress/components';
import { ImportCard } from './import-card';
import { ExportCard } from './export-card';
import { PhpResult, PhpResultBackLink } from './php-result';

/**
 * Renders the redesigned Tools screen.
 *
 * @param {Object} props        Component props.
 * @param {Object} props.config Data injected by the server.
 * @return {JSX.Element} The tools screen.
 */
export function ToolsScreen( { config } ) {
	const phpExport = ( config.phpExport || {} ).code;

	if ( phpExport ) {
		return (
			<>
				<PhpResultBackLink />
				<PhpResult code={ phpExport } />
			</>
		);
	}

	return (
		<Flex
			direction="column"
			align="stretch"
			gap={ 4 }
			className="scf-tools-redesign__stack"
			data-testid="tools-stack"
		>
			<FlexItem>
				<ImportCard config={ config } />
			</FlexItem>
			<FlexItem>
				<ExportCard config={ config } />
			</FlexItem>
		</Flex>
	);
}
