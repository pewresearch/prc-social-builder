import { ToggleControl, CheckboxControl } from '@wordpress/components';
import { useEntityRecord } from '@wordpress/core-data';

/** One chapter row from report-package `multiSectionReport` meta. */
interface ReportPackageChapter {
	key: string;
	postId: number;
}
/** Minimal post shape for source-post lookup in the container block. */
interface SourcePostRecord {
	meta?: {
		multiSectionReport?: ReportPackageChapter[];
	};
	title?: {
		rendered?: string;
	};
}

/** Props for the ReportChildrenGroup component. */
interface ReportChildrenGroupProps {
	reportChildren: ReportPackageChapter[];
	unselectedChildren: string;
	onChange: (newSelected: number[]) => void;
}
/** Props for the ReportChildCheckbox component. */
interface ReportChildCheckboxProps {
	child: ReportPackageChapter;
	isSelected: boolean;
	onChange: (postId: number, checked: boolean) => void;
}
/** Props for the SelectReportChildren component. */
interface SelectReportChildrenProps {
	sourcePostId: number;
	includeReportChildren: boolean;
	unselectedReportChildren: string;
	setIncludeChildren: (attrs: { includeReportChildren: boolean }) => void;
	setUnselectedChildren: (attrs: {
		unselectedReportChildren: string;
	}) => void;
}
/** Type for the unselected children list. */
type unselectedChildrenList = number[];

function parseUnselectedChildren(
	unselectedChildren: string
): unselectedChildrenList {
	if (!unselectedChildren) {
		return [];
	}

	try {
		const parsed = JSON.parse(unselectedChildren);
		return Array.isArray(parsed)
			? parsed.filter((id) => typeof id === 'number')
			: [];
	} catch {
		return [];
	}
}

// Component to display a report child checkbox.
function ReportChildCheckbox({
	child,
	isSelected,
	onChange,
}: ReportChildCheckboxProps) {
	const { record, hasResolved } = useEntityRecord<SourcePostRecord>(
		'postType',
		'post',
		child.postId,
		{ enabled: child.postId > 0 }
	);

	let postTitle = 'Loading title...';
	if (hasResolved) {
		postTitle =
			record?.title?.rendered ||
			'No title found for Post #' + child.postId;
	}

	return (
		<CheckboxControl
			label={postTitle}
			checked={isSelected}
			onChange={(checked) => onChange(child.postId, checked)}
		/>
	);
}

// Component to display a group of report children.
function ReportChildrenGroup({
	reportChildren,
	unselectedChildren,
	onChange,
}: ReportChildrenGroupProps) {
	const unselectedIds = new Set(parseUnselectedChildren(unselectedChildren));

	const updateChildren = (postId: number, checked: boolean) => {
		const children = new Set<number>(unselectedIds);
		if (checked) {
			children.delete(postId);
		} else {
			children.add(postId);
		}
		onChange([...children]);
	};

	return (
		<>
			{reportChildren.map((child) => {
				const isSelectedChild = !unselectedIds.has(child.postId);
				return (
					<ReportChildCheckbox
						key={child.key || child.postId}
						child={child}
						isSelected={isSelectedChild}
						onChange={updateChildren}
					/>
				);
			})}
		</>
	);
}

// Component to select report children.
export function SelectReportChildren({
	sourcePostId,
	includeReportChildren,
	unselectedReportChildren,
	setIncludeChildren,
	setUnselectedChildren,
}: SelectReportChildrenProps) {
	const { record, hasResolved } = useEntityRecord<SourcePostRecord>(
		'postType',
		'post',
		sourcePostId,
		{ enabled: sourcePostId > 0 }
	);

	const isMultiSectionReport =
		hasResolved &&
		Array.isArray(record?.meta?.multiSectionReport) &&
		record.meta.multiSectionReport.length > 0;
	const reportChildren = isMultiSectionReport
		? record.meta.multiSectionReport
		: [];

	if (!hasResolved) {
		return <p>Looking up report info...</p>;
	} else if (!isMultiSectionReport) {
		return <p>No report children found.</p>;
	}

	return (
		<>
			<ToggleControl
				label="Use Report Child Posts?"
				checked={includeReportChildren}
				onChange={(newIncludeReportChildren) =>
					setIncludeChildren({
						includeReportChildren: newIncludeReportChildren,
					})
				}
			/>
			{includeReportChildren && (
				<ReportChildrenGroup
					reportChildren={reportChildren}
					unselectedChildren={unselectedReportChildren}
					onChange={(ids) => {
						setUnselectedChildren({
							unselectedReportChildren: JSON.stringify(ids),
						});
					}}
				/>
			)}
		</>
	);
}
