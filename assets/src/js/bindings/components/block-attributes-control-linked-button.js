/**
 * WordPress dependencies
 */
import { link, linkOff } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const BlockAttributesControlLinkedButton = (
	props
) => {
	const { className, isLinked, onClick } = props;

	const label = isLinked ? __( 'Unlink sides' ) : __( 'Link sides' );

	return (
		<Button
			size="small"
			icon={ isLinked ? link : linkOff }
			iconSize={ 24 }
			label={ label }
			className={ className }
			onClick ={ onClick }
		/>
	);
};

export default BlockAttributesControlLinkedButton;