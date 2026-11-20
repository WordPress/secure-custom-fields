/**
 * A settings field row: label column and control column.
 *
 * @since SCF 6.9.10
 */

import {
	ExternalLink,
	SelectControl,
	TextControl,
	ToggleControl,
	Tooltip,
} from '@wordpress/components';

function Badge( { children, tone = 'default' } ) {
	return (
		<span className={ `scf-settings-badge is-${ tone }` }>
			{ children }
		</span>
	);
}

function ManagedBadge( { strings } ) {
	const badge = <Badge>{ strings.managedViaCode }</Badge>;

	if ( ! strings.managedViaCodeTooltip ) {
		return badge;
	}

	return (
		<Tooltip text={ strings.managedViaCodeTooltip }>
			{ /* Focusable, so keyboard users can reach the tooltip too. */ }
			<span className="scf-settings-badge-trigger" tabIndex={ 0 }>
				{ badge }
			</span>
		</Tooltip>
	);
}

function LearnMoreLink( { url, label, strings } ) {
	const ariaLabel = strings.learnMoreAbout
		? `${ strings.learnMoreAbout.replace( '%s', label ) } ${
				strings.opensInNewTab || ''
		  }`.trim()
		: undefined;

	return (
		<ExternalLink href={ url } aria-label={ ariaLabel }>
			{ strings.learnMore }
		</ExternalLink>
	);
}

function FieldControl( { field, value, disabled, onChange } ) {
	if ( field.type === 'toggle' ) {
		return (
			<ToggleControl
				__nextHasNoMarginBottom
				label={
					<span className="screen-reader-text">{ field.label }</span>
				}
				checked={ !! value }
				disabled={ disabled }
				onChange={ onChange }
			/>
		);
	}

	if ( field.type === 'select' ) {
		return (
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				hideLabelFromVision
				label={ field.label }
				value={ value === null || value === undefined ? '' : value }
				options={ field.options || [] }
				disabled={ disabled }
				onChange={ onChange }
			/>
		);
	}

	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			hideLabelFromVision
			label={ field.label }
			value={ value === null || value === undefined ? '' : value }
			disabled={ disabled }
			onChange={ onChange }
		/>
	);
}

/**
 * Renders one field from the 'acf/settings_page/general/{section}/fields' filter as a row.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.field    The field.
 * @param {*}        props.value    The current value.
 * @param {Object}   props.strings  Translated strings.
 * @param {Function} props.onChange Called with the new value.
 */
export default function FieldRow( { field, value, strings, onChange } ) {
	const isToggle = field.type === 'toggle';
	const disabled = !! ( field.managed || field.disabled );

	return (
		<div className="scf-settings-row" data-setting={ field.key }>
			<div className="scf-settings-row__label">
				<span className="scf-settings-row__title">{ field.label }</span>
				{ field.experimental && (
					<Badge tone="warning">{ strings.experimental }</Badge>
				) }
			</div>
			<div
				className={ `scf-settings-row__content ${
					isToggle ? 'is-toggle' : 'is-input'
				}` }
			>
				<div className="scf-settings-row__control">
					<FieldControl
						field={ field }
						value={ value }
						disabled={ disabled }
						onChange={ onChange }
					/>
				</div>
				<div className="scf-settings-row__help">
					{ field.description && (
						<span className="scf-settings-row__description">
							{ field.description }{ ' ' }
						</span>
					) }
					{ field.learnMoreUrl && (
						<LearnMoreLink
							url={ field.learnMoreUrl }
							label={ field.label }
							strings={ strings }
						/>
					) }
					{ field.managed && (
						<div className="scf-settings-row__badges">
							<ManagedBadge strings={ strings } />
						</div>
					) }
				</div>
			</div>
		</div>
	);
}
