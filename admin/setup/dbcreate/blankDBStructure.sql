-- --------------------------------------------------------

-- TO DO: After creating the structure from this file we need to:
--
--        1. create referential constraints with AddReferentialConstraints.sql
--        2. add stored procedures from AddProceduresTriggers.sql
--        3. import core content (minimal database definitions) from
--           admin/setup/dbcreate/coreDefinitions.txt

-- The next section of this file is a PHPMyAdmin dump of database structure
-- DO NOT include referential integrity or triggers/procedures. Turn off use of
-- backquotes, auto increment value and dumping of actual data

-- ***************************************************************************

--    *  blankDBStructureDefinitionsOnly.sql must be updated at the same time
--    *  if there is any change of structure

--    * Database structure version: 1.1.0
--    * Database structure version: 1.2.0  @ 14/9/2017, 
--    * Database structure version: 1.3.6  @ 20/10/2022, 
--    * Database structure version: 1.3.10  @ 05/04/2023, 
--    * Database structure version: 1.3.16  @ 27/08/2024, 

--    * THE INSERTION STATEMENTS AT THE END ARE * NOT * PART OF THE DUMP
--    * DO NOT DELETE THEM

-- ***************************************************************************

-- phpMyAdmin SQL Dump

-- phpMyAdmin SQL Dump
-- version 2.9.0.2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 19, 2012 at 12:14 PM
-- Server version: 5.0.51
-- PHP Version: 5.2.3
--
-- Database: 'hdb_HeuristCoreDefinitions'
--

-- --------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS= 0;
SET UNIQUE_CHECKS = 0;
START TRANSACTION;
--  COLLATE utf8mb4_0900_ai_ci

--
-- Table structure for table 'Records'
--

CREATE TABLE Records (
  rec_ID int unsigned NOT NULL auto_increment COMMENT 'The primary record ID, also called, historically, bib_id',
  rec_URL varchar(2000) default NULL COMMENT 'The primary URL pointed to by this record (particularly for Internet bookmarks)',
  rec_Added datetime NOT NULL default '1000-01-01 00:00:00' COMMENT 'Date and time record added',
  rec_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time the record was modified',
  rec_Title varchar(1023) NOT NULL default '' COMMENT 'Composite (constructed) title of the record, used for display and search',
  rec_ScratchPad text COMMENT 'Scratchpad, mainly for text captured with bookmarklet',
  rec_RecTypeID smallint unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  rec_AddedByUGrpID smallint unsigned default NULL COMMENT 'ID of the user who created the record',
  rec_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Whether added by an import (value 1) or by manual entry (value 0)',
  rec_Popularity int unsigned NOT NULL default '0' COMMENT 'Calculated popularity rating for sorting order, set by cron job',
  rec_FlagTemporary tinyint unsigned NOT NULL default '0' COMMENT 'Flags a partially created record before fully populated',
  rec_OwnerUGrpID smallint unsigned NOT NULL default '0' COMMENT 'Workgroup which owns this record, 0 = everyone',
  rec_NonOwnerVisibility enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Defines if record visible outside owning Workgroup(s) or to anyone',
  rec_URLLastVerified datetime default NULL COMMENT 'Last date time when URL was verified as contactable',
  rec_URLErrorMessage varchar(255) default NULL COMMENT 'Error returned by URL checking script for bad/inaccessible URLs',
  rec_URLExtensionForMimeType varchar(10) default NULL COMMENT 'A mime type extension for multimedia files pointed to DIRECTLY by the record URL',
  rec_Hash varchar(60) default NULL COMMENT 'A composite truncated metaphones + numeric values hash of significant fields',
  PRIMARY KEY  (rec_ID),
  KEY rec_URL (rec_URL(63)),
  KEY rec_Title (rec_Title(63)),
  KEY rec_RecTypeID (rec_RecTypeID),
  KEY rec_Modified (rec_Modified),
  KEY rec_OwnerUGrpID (rec_OwnerUGrpID),
  KEY rec_Hash (rec_Hash(40)),
  KEY rec_AddedByUGrpID (rec_AddedByUGrpID),
  FULLTEXT KEY rec_Title_FullText (rec_Title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table 'defCalcFunctions'
--

CREATE TABLE defCalcFunctions (
  cfn_ID smallint unsigned NOT NULL auto_increment COMMENT 'Primary key of defCalcFunctions table',
  cfn_Name varchar(63) NOT NULL COMMENT 'Descriptive name for function',
  cfn_Domain enum('calcfieldstring','pluginphp') NOT NULL default 'calcfieldstring' COMMENT 'Domain of application of this function specification',
  cfn_FunctionSpecification text COMMENT 'A function or chain of functions, or some PHP plugin code',
  cfn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  cfn_RecTypeIDs varchar(250) default NULL COMMENT 'CSV list of Rectype IDs that participate in formula',
  PRIMARY KEY  (cfn_ID)
) ENGINE=InnoDB COMMENT='Specifications for generating calculated fields';

-- --------------------------------------------------------

--
-- Table structure for table 'defCrosswalk'
--

CREATE TABLE defCrosswalk (
  crw_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Primary key',
  crw_SourcedbID mediumint unsigned NOT NULL COMMENT 'The Heurist reference ID of the database containing the definition being mapped',
  crw_SourceCode mediumint unsigned NOT NULL COMMENT 'The code of the definition in the source database',
  crw_DefType enum('rectype','constraint','detailtype','recstructure','ontology','vocabulary','term') NOT NULL COMMENT 'The type of code being mapped between the source and this database',
  crw_LocalCode mediumint unsigned NOT NULL COMMENT 'The corresponding code in the local database',
  crw_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date when this mapping added or modified',
  PRIMARY KEY  (crw_ID),
  UNIQUE KEY crw_composite (crw_SourcedbID,crw_DefType,crw_LocalCode)
) ENGINE=InnoDB COMMENT='Map the codes used in this Heurist DB to codes used in other';

-- --------------------------------------------------------

--
-- Table structure for table 'defDetailTypeGroups'
--

CREATE TABLE defDetailTypeGroups (
  dtg_ID tinyint unsigned NOT NULL auto_increment COMMENT 'Primary ID - Code for detail type groups',
  dtg_Name varchar(63) NOT NULL COMMENT 'Descriptive heading to be displayed for each group of details (fields)',
  dtg_Order tinyint unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of detail type groups within pulldown lists',
  dtg_Description varchar(255) NOT NULL COMMENT 'General description fo this group of detail (field) types',
  dtg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (dtg_ID)
) ENGINE=InnoDB COMMENT='Groups detail types for display in separate sections of edit';

-- --------------------------------------------------------

--
-- Table structure for table 'defDetailTypes'
--

CREATE TABLE defDetailTypes (
  dty_ID smallint unsigned NOT NULL auto_increment COMMENT 'Code for the detail type (field) - may vary between Heurist DBs',
  dty_Name varchar(255) NOT NULL COMMENT 'The canonical (standard) name of the detail type, used as default in edit form',
  dty_Documentation varchar(5000) default 'Please document the nature of this detail type (field)) ...' COMMENT 'Documentation of the detail type, what it means, how defined',
  dty_Type enum('freetext','blocktext','integer','date','year','relmarker','boolean','enum','relationtype','resource','float','file','geo','separator','calculated','fieldsetmarker','urlinclude') NOT NULL COMMENT 'The value-type of this detail type, what sort of data is stored',
  dty_HelpText varchar(255) NOT NULL default 'Please provide a short explanation for the user ...' COMMENT 'The default help text displayed to the user under the field',
  dty_ExtendedDescription varchar(5000) default 'You can add additional information in extended description for display on rollover' COMMENT 'Extended text describing this detail type, for display in rollover',
  dty_EntryMask text COMMENT 'Data entry mask, use to control decimals on numeric values, content of text fields etc.',
  dty_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  dty_OriginatingDBID mediumint unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  dty_NameInOriginatingDB varchar(255) default NULL COMMENT 'Name used in database where this detail type originated',
  dty_IDInOriginatingDB smallint unsigned default NULL COMMENT 'ID used in database where this detail type originated',
  dty_DetailTypeGroupID tinyint unsigned NOT NULL default '1' COMMENT 'The general role of this detail allowing differentiated lists of detail types',
  dty_OrderInGroup tinyint unsigned default '0' COMMENT 'The display order of DetailType within group, alphabetic if equal values',
  dty_JsonTermIDTree varchar(5000) default NULL COMMENT 'Tree of Term IDs to show for this field (display-only header terms set in HeaderTermIDs)',
  dty_TermIDTreeNonSelectableIDs varchar(1000) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  dty_PtrTargetRectypeIDs varchar(250) default NULL COMMENT 'CSVlist of target Rectype IDs, null = any',
  dty_FieldSetRectypeID smallint unsigned default NULL COMMENT 'For a FieldSetMarker, the record type to be inserted as a fieldset',
  dty_ShowInLists tinyint unsigned NOT NULL default '1' COMMENT 'Flags if detail type is to be shown in end-user interface, 1=yes',
  dty_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows restriction of visibility of a particular field in ALL record types (overrides rst_VisibleOutsideGroup)',
  dty_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  dty_LocallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  dty_SemanticReferenceURL VARCHAR( 250 ) NULL COMMENT 'The URI to a semantic definition or web page describing the base field type',  
  PRIMARY KEY  (dty_ID),
  UNIQUE KEY dty_Name (dty_Name),
  KEY dty_Type (dty_Type),
  KEY dty_DetailTypeGroupID (dty_DetailTypeGroupID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The detail types (fields) which can be attached to records';

-- --------------------------------------------------------

--
-- Table structure for table 'defFileExtToMimetype'
--

CREATE TABLE defFileExtToMimetype (
  fxm_Extension varchar(10) NOT NULL COMMENT 'The file extension, indicates mimetype, icon and some beahviours',
  fxm_MimeType varchar(100) NOT NULL COMMENT 'The standard mime type string',
  fxm_OpenNewWindow tinyint unsigned NOT NULL default '0' COMMENT 'Flag if a new window should be opened to display this mimetype',
  fxm_IconFileName varchar(31) default NULL COMMENT 'Filename of the icon file for this mimetype (shared by several)',
  fxm_FiletypeName varchar(31) default NULL COMMENT 'A textual name for the file type represented by the extension',
  fxm_ImagePlaceholder varchar(63) default NULL COMMENT 'Thumbnail size representation for display, generate from fxm_FiletypeName',
  fxm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (fxm_Extension)
) ENGINE=InnoDB COMMENT='Converts extensions to mimetypes and provides icons and mime';

-- --------------------------------------------------------

--
-- Table structure for table 'defOntologies'
--

CREATE TABLE defOntologies (
  ont_ID smallint unsigned NOT NULL auto_increment COMMENT 'Ontology code, primary key',
  ont_ShortName varchar(63) NOT NULL COMMENT 'The commonly used acronym or short name of the ontology',
  ont_FullName varchar(255) NOT NULL COMMENT 'The commonly used full name of the ontology',
  ont_Description varchar(1000) default NULL COMMENT 'An optional descriptuion of the domain, origina and aims of the ontology',
  ont_RefURI varchar(250) default NULL COMMENT 'The URI to a definition of the ontology',
  ont_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  ont_OriginatingDBID mediumint unsigned default NULL COMMENT 'Database where this ontology originated, 0 = locally',
  ont_NameInOriginatingDB varchar(63) default NULL COMMENT 'Name used in database where this ontology originated',
  ont_IDInOriginatingDB smallint unsigned default NULL COMMENT 'ID used in database where this ontology originated',
  ont_Order tinyint unsigned zerofill NOT NULL default '255' COMMENT 'Ordering value to define alternate display order in interface',
  ont_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  ont_locallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (ont_ID),
  UNIQUE KEY ont_ShortName (ont_ShortName)
) ENGINE=InnoDB COMMENT='A table of references to different ontologies used by Heuris';

-- --------------------------------------------------------

--
-- Table structure for table 'defRecStructure'
--

CREATE TABLE defRecStructure (
  rst_ID smallint unsigned NOT NULL auto_increment COMMENT 'Primary key for the record structures table',
  rst_RecTypeID smallint unsigned NOT NULL COMMENT 'The record type to which this detail is allocated, 0 = all rectypes',
  rst_DetailTypeID smallint unsigned NOT NULL COMMENT 'Detail type for this field or, if MSB set, FieldSet code + 32767',
  rst_DisplayName varchar(255) NOT NULL default 'Please enter a prompt ...' COMMENT 'Display name for this dtl type in this rectype, autofill with dty_Name',
  rst_DisplayHelpText varchar(255) default NULL COMMENT 'The user help text to be displayed for this detail type for this record type',
  rst_DisplayExtendedDescription varchar(5000) default NULL COMMENT 'The rollover text to be displayed for this detail type for this record type',
  rst_DisplayOrder smallint unsigned zerofill NOT NULL default '999' COMMENT 'A sort order for display of this detail type in the record edit form',
  rst_DisplayWidth tinyint unsigned NOT NULL default '100' COMMENT 'The field width displayed for this detail type in this record type',
  rst_DisplayHeight tinyint unsigned NOT NULL default '3' COMMENT 'The field height for this detail type in this record type, only relevant for memo fields',
  rst_DefaultValue text default NULL COMMENT 'The default value for this detail type for this record type',
  rst_RecordMatchOrder tinyint unsigned NOT NULL default '0' COMMENT 'Indicates order of significance in detecting duplicate records, 1 = highest',
  rst_CalcFunctionID tinyint unsigned default NULL COMMENT 'FK to table of function specifications for calculating string values',
  rst_CalcFieldMask varchar(250) NULL COMMENT 'A mask string along the lines of the title mask allowing a composite field to be generated from other fields in the record',
  rst_RequirementType enum('required','recommended','optional','forbidden') NOT NULL default 'optional',
  rst_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'public' COMMENT 'Allows restriction of visibility of a particular field in a specified record type',
  rst_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rst_MayModify enum('locked','discouraged','open') NOT NULL default 'open' COMMENT 'Extent to which detail may be modified within this record structure',
  rst_OriginatingDBID mediumint unsigned default NULL COMMENT 'Database where this record structure element originated, 0 = locally',
  rst_IDInOriginatingDB smallint unsigned default NULL COMMENT 'ID used in database where this record structure element originated',
  rst_MaxValues tinyint unsigned default NULL COMMENT 'Maximum number of values per record for this detail, 1 - single, >1 limited, NULL or 0 = no limit',
  rst_MinValues tinyint unsigned NOT NULL default '0' COMMENT 'If required, minimum number of values per record for this detail',
  rst_InitialRepeats TINYINT DEFAULT 1 COMMENT 'Number of repeat values to be displayed for this field when a new record is first displayed',
  rst_DisplayDetailTypeGroupID tinyint unsigned default NULL COMMENT 'If set, places detail in specified group instead of according to dty_DetailTypeGroup',
  rst_FilteredJsonTermIDTree varchar(500) default NULL COMMENT 'JSON encoded tree of allowed terms, subset of those defined in defDetailType. This field is no longer used',
  rst_PtrFilteredIDs varchar(250) default NULL COMMENT 'Allowed Rectypes (CSV) within list defined by defDetailType (for pointer details) This field is no longer used',
  rst_CreateChildIfRecPtr tinyint unsigned NOT NULL default '0' COMMENT 'For pointer fields, flags that new records created from this field should be marked as children of the creating record',
  rst_PointerMode enum('dropdown_add','dropdown','addorbrowse','addonly','browseonly') DEFAULT 'dropdown_add' COMMENT 'When adding record pointer values, default or null = show both add and browse, otherwise only allow add or only allow browse-for-existing',
  rst_PointerBrowseFilter varchar(255)  DEFAULT NULL COMMENT 'When adding record pointer values, defines a Heurist filter to restrict the list of target records browsed',   
  rst_OrderForThumbnailGeneration tinyint unsigned default NULL COMMENT 'Priority order of fields to use in generating thumbnail, null = do not use',
  rst_TermIDTreeNonSelectableIDs varchar(255) default NULL COMMENT 'Term IDs to use as non-selectable headers for this field',
  rst_ShowDetailCertainty tinyint NOT NULL  default 0 COMMENT 'When editing the field, allow editng of the dtl_Certainty value (off by default)', 
  rst_ShowDetailAnnotation tinyint unsigned NOT NULL default 0 COMMENT 'When editing the field, allow editng of the dtl_Annotation value (off by default)',
  rst_NumericLargestValueUsed INTEGER NULL COMMENT 'For numeric fields, Null = no auto increment, 0 or more indicates largest value used so far. Set to 0 to switch on incrementing',
  rst_EntryMask Varchar(250) NULL COMMENT 'Data entry mask, use to control decimals on numeric values, content of text fields etc. for this record type - future implementation Aug 2017',
  rst_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rst_LocallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  rst_SemanticReferenceURL VARCHAR( 250 ) NULL COMMENT 'The URI to a semantic definition or web page describing this field used within this record type',
  rst_TermsAsButtons tinyint default '0' Comment 'If 1, term list fields are represented as buttons (if single value) or checkboxes (if repeat values)',  
  PRIMARY KEY  (rst_ID),
  UNIQUE KEY rst_composite (rst_RecTypeID,rst_DetailTypeID),
  KEY rst_DetailTypeID (rst_DetailTypeID)
) ENGINE=InnoDB COMMENT='The record details (fields) required for each record type';

-- --------------------------------------------------------

--
-- Table structure for table 'defRecTypeGroups'
--

CREATE TABLE defRecTypeGroups (
  rtg_ID tinyint unsigned NOT NULL auto_increment COMMENT 'Record type group ID referenced in defRectypes',
  rtg_Name varchar(40) NOT NULL COMMENT 'Name for this group of record types, shown as heading in lists',
  rtg_Domain enum('functionalgroup','modelview') NOT NULL default 'functionalgroup' COMMENT 'functionalgroup is normal record types group, modelview group for visualising actual structure based on data',
  rtg_Order tinyint unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of record type groups within pulldown lists',
  rtg_Description varchar(250) default NULL COMMENT 'A description of the record type group and its purpose',
  rtg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (rtg_ID),
  UNIQUE KEY rtg_Name (rtg_Name)
) ENGINE=InnoDB COMMENT='Grouping mechanism for record types in pulldowns';

-- --------------------------------------------------------

--
-- Table structure for table 'defRecTypes'
--

CREATE TABLE defRecTypes (
  rty_ID smallint unsigned NOT NULL auto_increment COMMENT 'Record type code, widely used to reference record types, primary key',
  rty_Name varchar(63) NOT NULL COMMENT 'The name which is used to describe this record (object) type',
  rty_OrderInGroup tinyint unsigned default '0' COMMENT 'Ordering within record type display groups for pulldowns',
  rty_Description varchar(5000) NOT NULL COMMENT 'Description of this record type',
  rty_TitleMask varchar(500) NOT NULL default '[title]' COMMENT 'Mask to build a composite title by combining field values',
  rty_CanonicalTitleMask varchar(500) default '160' COMMENT 'Version of the mask converted to detail codes for processing',
  rty_Plural varchar(63) default NULL COMMENT 'Plural form of the record type name, manually entered',
  rty_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rty_OriginatingDBID mediumint unsigned default NULL COMMENT 'Database where this record type originated, 0 = locally',
  rty_NameInOriginatingDB varchar(63) default NULL COMMENT 'Name used in database where this record type originated',
  rty_IDInOriginatingDB smallint unsigned default NULL COMMENT 'ID in database where this record type originated',
  rty_NonOwnerVisibility enum('hidden','viewable','public','pending') NOT NULL default 'viewable' COMMENT 'Allows blanket restriction of visibility of a particular record type',
  rty_ShowInLists tinyint unsigned NOT NULL default '1' COMMENT 'Flags if record type is to be shown in end-user interface, 1=yes',
  rty_RecTypeGroupID tinyint unsigned NOT NULL default '1' COMMENT 'Record type group to which this record type belongs',
  rty_RecTypeModelIDs varchar(63) default NULL COMMENT 'The model group(s) to which this rectype belongs, comma sep. list',
  rty_FlagAsFieldset tinyint unsigned NOT NULL default '0' COMMENT '0 = full record type, 1 = Fieldset = set of fields to include in other rectypes',
  rty_ReferenceURL varchar(250) default NULL COMMENT 'A semantic reference URI for, or a URL describing, the record type',
  rty_AlternativeRecEditor varchar(63) default NULL COMMENT 'Name or URL of alternative record editor function to be used for this rectype',
  rty_Type enum('normal','relationship','dummy') NOT NULL default 'normal' COMMENT 'Use to flag special record types to trigger special functions',
  rty_ShowURLOnEditForm tinyint NOT NULL default '1' COMMENT 'Determines whether special URL field is shown at the top of the edit form',
  rty_ShowDescriptionOnEditForm tinyint NOT NULL default '1' COMMENT 'Determines whether the record type description field is shown at the top of the edit form',
  rty_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rty_LocallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (rty_ID),
  UNIQUE KEY rty_Name (rty_Name),
  KEY rty_RecTypeGroupID (rty_RecTypeGroupID)
) ENGINE=InnoDB COMMENT='Defines record types, which corresponds with a set of detail';


-- --------------------------------------------------------

--
-- Table structure for table 'defRelationshipConstraints'
--

CREATE TABLE defRelationshipConstraints (
  rcs_ID smallint unsigned NOT NULL auto_increment COMMENT 'Record-detailtype constraint table primary key',
  rcs_SourceRectypeID smallint unsigned default NULL COMMENT 'Source record type for this constraint, Null = all types',
  rcs_TargetRectypeID smallint unsigned default NULL COMMENT 'Target record type pointed to by relationship record, Null = all types',
  rcs_Description varchar(1000) default 'Please describe ...',
  rcs_RelationshipsLimit tinyint unsigned default NULL COMMENT 'Deprecated: Null= no limit; 0=forbidden, 1, 2 ... =max # of relationship records per record per detailtype/rectypes triplet',
  rcs_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  rcs_OriginatingDBID mediumint unsigned NOT NULL default '0' COMMENT 'Database where this constraint originated, 0 or local db code = locally',
  rcs_IDInOriginatingDB smallint unsigned default '0' COMMENT 'Code used in database where this constraint originated',
  rcs_TermID int unsigned default NULL COMMENT 'The ID of a term to be constrained, applies to descendants unless they have more specific',
  rcs_TermLimit tinyint unsigned default NULL COMMENT 'Null=none 0=not allowed 1,2..=max # times a term from termSet ident. by termID can be used',
  rcs_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  rcs_LocallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  PRIMARY KEY  (rcs_ID),
  UNIQUE KEY rcs_CompositeKey (rcs_SourceRectypeID,rcs_TargetRectypeID,rcs_TermID),
  KEY rcs_TermID (rcs_TermID),
  KEY rcs_TargetRectypeID (rcs_TargetRectypeID),
  KEY rcs_SourceRecTypeID (rcs_SourceRectypeID)
) ENGINE=InnoDB COMMENT='Constrain target-rectype/vocabularies/values for a pointer d';

-- --------------------------------------------------------
--
-- Table structure for table 'defVocabularyGroups'
--

CREATE TABLE defVocabularyGroups (
  vcg_ID tinyint unsigned NOT NULL auto_increment COMMENT 'Vocabulary group ID referenced in vocabs editor',
  vcg_Name varchar(40) NOT NULL COMMENT 'Name for this group of vocabularies, shown as heading in lists',
  vcg_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Normal vocabularies are termed enum, relational are for relationship types but can also be used as normal vocabularies',
  vcg_Order tinyint unsigned zerofill NOT NULL default '002' COMMENT 'Ordering of vocabulary groups within pulldown lists',
  vcg_Description varchar(250) default NULL COMMENT 'A description of the vocabulary group and its purpose',
  vcg_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this vocabulary group record, used to get last updated date for table',
  PRIMARY KEY  (vcg_ID),
  UNIQUE KEY vcg_Name (vcg_Name)
) ENGINE=InnoDB COMMENT='Grouping mechanism for vocabularies in vocabularies/terms editor';

-- --------------------------------------------------------

--
-- Table structure for table 'defTerms'
--

CREATE TABLE defTerms (
  trm_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key, the term code used in the detail record',
  trm_Label varchar(250) NOT NULL COMMENT 'Human readable term used in the interface, cannot be blank',
  trm_InverseTermID int unsigned default NULL COMMENT 'ID for the inverse value (relationships), null if no inverse',
  trm_Description varchar(1000) default NULL COMMENT 'A description/gloss on the meaning of the term',
  trm_Status enum('reserved','approved','pending','open') NOT NULL default 'open' COMMENT 'Reserved Heurist codes, approved/pending by ''Board'', and user additions',
  trm_OriginatingDBID mediumint unsigned default NULL COMMENT 'Database where this detail type originated, 0 = locally',
  trm_NameInOriginatingDB varchar(250) default NULL COMMENT 'Name (label) for this term in originating database',
  trm_IDInOriginatingDB mediumint unsigned default NULL COMMENT 'ID used in database where this  term originated',
  trm_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Set to 1 if term added by an import, otherwise 0',
  trm_IsLocalExtension tinyint unsigned NOT NULL default '0' COMMENT 'Flag that this value not in the externally referenced vocabulary',
  trm_Domain enum('enum','relation') NOT NULL default 'enum' COMMENT 'Define the usage of the term',
  trm_OntID smallint unsigned NOT NULL default '0' COMMENT 'Ontology from which this vocabulary originated, 0 = locally defined ontology',
  trm_ChildCount tinyint NOT NULL default '0' COMMENT 'Stores the count of children, updated whenever children are added/removed',
  trm_ParentTermID int unsigned default NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trm_Depth tinyint unsigned NOT NULL default '1' COMMENT 'Depth of term in the term tree, should always be 1+parent depth',
  trm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  trm_LocallyModified tinyint unsigned NOT NULL default '0' COMMENT 'Flags a definition element which has been modified relative to the original source',
  trm_Code varchar(100) default NULL COMMENT 'Optional code eg. alphanumeric code which may be required for import or export',
  trm_SemanticReferenceURL VARCHAR( 250 ) NULL COMMENT 'The URI to a semantic definition or web page describing the term',
  trm_IllustrationURL VARCHAR( 250 ) NULL COMMENT 'The URL to a picture or other resource illustrating the term. If blank, look for trm_ID.jpg/gif/png in term_images directory',  
  trm_VocabularyGroupID smallint unsigned NULL default '0' COMMENT 'Vocabulary group to which this term belongs, if a top level term (vocabulary)',
  trm_OrderInBranch smallint NULL Comment 'Defines sorting order of terms if non-alphabetic. Operates only within a single branch, including root',
  PRIMARY KEY  (trm_ID),
  KEY trm_ParentTermIDKey (trm_ParentTermID),
  KEY trm_InverseTermIDKey (trm_InverseTermID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4 COMMENT='Terms by detail type and the vocabulary they belong to';

-- --------------------------------------------------------

--
-- Table structure for table 'defTermsLinks'
--

CREATE TABLE defTermsLinks (
  trl_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Primary key for vocabulary-terms hierarchy',
  trl_ParentID smallint unsigned NOT NULL COMMENT 'The ID of the parent/owner term in the hierarchy',
  trl_TermID smallint unsigned NOT NULL COMMENT 'The ID of the child term',
  PRIMARY KEY  (trl_ID),
  UNIQUE KEY trl_CompositeKey (trl_ParentID,trl_TermID)
) ENGINE=InnoDB COMMENT='Identifies hierarchy of vocabularies and terms';


-- --------------------------------------------------------

--
-- Table structure for table 'defTranslations'
--

CREATE TABLE defTranslations (
  trn_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key of defTranslations table',
  trn_Source varchar(64) NOT NULL COMMENT 'The column to be translated (unique names identify source)',
  trn_Code int unsigned NOT NULL COMMENT 'The primary key / ID in the table containing the text to be translated',
  trn_LanguageCode char(3) NOT NULL COMMENT 'The translation language code ISO639',
  trn_Translation text NOT NULL COMMENT 'The translation of the text in this location (table/field/id)',
  trn_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (trn_ID),
  UNIQUE KEY trn_composite (trn_Source,trn_Code,trn_LanguageCode),
  KEY trn_LanguageCode (trn_LanguageCode)
) ENGINE=InnoDB COMMENT='Translation table into multiple languages for all translatab';

-- --------------------------------------------------------

--
-- Table structure for table 'defURLPrefixes'
--

CREATE TABLE defURLPrefixes (
  urp_ID smallint unsigned NOT NULL auto_increment COMMENT 'ID which will be stored as proxy for the URL prefix',
  urp_Prefix varchar(250) NOT NULL COMMENT 'URL prefix which is prepended to record URLs',
  urp_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (urp_ID),
  UNIQUE KEY urp_Prefix (urp_Prefix)
) ENGINE=InnoDB COMMENT='Common URL prefixes allowing single-point change of URL for ';

-- --------------------------------------------------------

--
-- Table structure for table 'recDetails'
--

CREATE TABLE recDetails (
  dtl_ID integer unsigned NOT NULL auto_increment COMMENT 'Primary key for record detail (field) values table',
  dtl_RecID integer unsigned NOT NULL COMMENT 'The record to which this detail (field) applies',
  dtl_DetailTypeID smallint unsigned NOT NULL COMMENT 'The detail type code identifying the type definition of data',
  dtl_Value text COMMENT 'The value of the detail as text (used for all except files and geometry)',
  dtl_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Set 1 if added by an import, set 0 if added by user during data entry',
  dtl_UploadedFileID mediumint unsigned default NULL COMMENT 'The numeric code = filename of an uploaded file ',
  dtl_Geo geometry default NULL COMMENT 'A geometry (spatial) object',
  dtl_ValShortened varchar(31) NOT NULL default '' COMMENT 'Truncated version of the textual value without spaces',
  dtl_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record detail, used to get last updated date for table',
  dtl_Certainty DECIMAL( 3, 2 ) NOT NULL DEFAULT '1.0' COMMENT 'A certainty value for this observation in the range 0 to 1, where 1 indicates complete certainty',
  dtl_Annotation VARCHAR( 250 ) NULL COMMENT 'A short note / annotation about this specific data value - may enlarge for example on the reasons for the certainty value',  
  dtl_HideFromPublic tinyint unsigned default NULL COMMENT 'If set, the value is not shown in Record View, column lists, custom reports or anywhere the value is displayed. It may still be used in filter or analysis',  
  PRIMARY KEY  (dtl_ID),
  KEY dtl_DetailtypeIDkey (dtl_DetailTypeID),
  KEY dtl_RecIDKey (dtl_RecID),
  KEY dtl_ValueKey (dtl_Value(63)),
  KEY dtl_UploadedFileIDKey (dtl_UploadedFileID),
  FULLTEXT KEY dtl_Value_FullText (dtl_Value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='The detail (field) values for each record - public data';
-- --------------------------------------------------------

--
-- Table structure for table 'recForwarding'
--

CREATE TABLE recForwarding (
  rfw_OldRecID int unsigned NOT NULL COMMENT 'The deleted record which will be redirected to another',
  rfw_NewRecID int unsigned NOT NULL COMMENT 'The new record to which this ID will be forwarded',
  PRIMARY KEY  (rfw_OldRecID),
  KEY rfw_NewRecID (rfw_NewRecID)
) ENGINE=InnoDB COMMENT='Allows referer routine to redirect certain calls to a replace';


-- --------------------------------------------------------

--
-- Table structure for table 'recLinks'
--

CREATE TABLE recLinks (
  rl_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key',
  rl_SourceID int unsigned NOT NULL COMMENT 'ID of one of the records, the source record in the case of a record pointer linking the records',
  rl_TargetID int unsigned NOT NULL COMMENT 'ID of the second record, the target record in the case of a record pointer linking the records',
  rl_RelationID int unsigned        COMMENT 'For relationship records: ID of the record linking the source and target records',
  rl_RelationTypeID int unsigned    COMMENT 'Term ID (defTerms.trm_ID) for the relationship type linking these records',
  rl_DetailTypeID int unsigned      COMMENT 'For record pointer links: Detail type ID for the record pointer field in the source directed towards the target record',
  rl_DetailID int unsigned          COMMENT 'Detail ID of the record pointer value linking these records',
  PRIMARY KEY  (rl_ID),
  KEY rl_SourcePtrKey (rl_SourceID),
  KEY rl_TargetPtrKey (rl_TargetID),
  KEY rl_RelationKey (rl_RelationID),
  KEY rl_DetailKey (rl_DetailID)
) ENGINE=InnoDB COMMENT='A cache for links between records (both record pointer fields and relationship records) to speed access';

-- --------------------------------------------------------

--
-- Table structure for table 'recDetailsDateIndex'
--

CREATE TABLE recDetailsDateIndex (
  rdi_ID   int unsigned NOT NULL auto_increment COMMENT 'Primary key',
  rdi_RecID int unsigned NOT NULL COMMENT 'Record ID',
  rdi_DetailTypeID int unsigned NOT NULL COMMENT 'Detail type ID',
  rdi_DetailID int unsigned NOT NULL COMMENT 'Detail ID',
  rdi_estMinDate DECIMAL(15,4) NOT NULL COMMENT '',
  rdi_estMaxDate DECIMAL(15,4) NOT NULL COMMENT '',
  PRIMARY KEY  (rdi_ID),
  KEY rdi_RecIDKey (rdi_RecID),
  KEY rdi_DetailTypeKey (rdi_DetailTypeID),
  KEY rdi_DetailIDKey (rdi_DetailID),
  KEY rdi_MinDateKey (rdi_estMinDate),
  KEY rdi_MaxDateKey (rdi_estMaxDate)
) ENGINE=InnoDB COMMENT='A cache for date fields to speed access';


-- --------------------------------------------------------

--
-- Table structure for table 'recSimilarButNotDupes'
--

CREATE TABLE recSimilarButNotDupes (
  snd_SimRecsList varchar(16000) NOT NULL COMMENT 'A comma separated list of record IDs which are similar but not identical',
  KEY snd_SimRecsList (snd_SimRecsList(13))
) ENGINE=InnoDB COMMENT='Used in dedupe. Sets of IDs which are not dupes. Index is of';

-- --------------------------------------------------------

--
-- Table structure for table 'recThreadedComments'
--

CREATE TABLE recThreadedComments (
  cmt_ID int unsigned NOT NULL auto_increment COMMENT 'Comment ID, primary key for comments',
  cmt_Text varchar(5000) NOT NULL COMMENT 'Text of comment',
  cmt_OwnerUgrpID smallint unsigned NOT NULL COMMENT 'User ID of user making comment',
  cmt_Added datetime NOT NULL default '1000-01-01 00:00:00' COMMENT 'Date and time of creation of comment',
  cmt_ParentCmtID int unsigned default NULL COMMENT 'Parent comment of this comment',
  cmt_Deleted tinyint unsigned NOT NULL default '0' COMMENT 'Flag deleted comments',
  cmt_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of comment',
  cmt_RecID int unsigned NOT NULL COMMENT 'Record ID to which this comment applies, required',
  PRIMARY KEY  (cmt_ID),
  KEY cmt_OwnerUgrpID (cmt_OwnerUgrpID),
  KEY cmt_ParentCmtID (cmt_ParentCmtID),
  KEY cmt_RecID (cmt_RecID)
) ENGINE=InnoDB COMMENT='Threaded comments for each record';

-- --------------------------------------------------------

--
-- Table structure for table 'recUploadedFiles'
--
CREATE TABLE recUploadedFiles (
  ulf_ID mediumint unsigned NOT NULL auto_increment COMMENT 'A unique numeric file ID used as filename to store the data on disk and should be different ids if shared',
  ulf_OrigFileName varchar(255) NOT NULL COMMENT 'The original name of the file uploaded',
  ulf_UploaderUGrpID smallint unsigned default NULL COMMENT 'The user who uploaded the file',
  ulf_Added datetime NOT NULL default '1000-01-01 00:00:00' COMMENT 'The date and time the file was uploaded',
  ulf_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date of last modification of the file description record, automatic update',
  ulf_ObfuscatedFileID varchar(40) default NULL COMMENT 'SHA-1 hash of ulf_ID and random number to block sequential file harvesting',
  ulf_ExternalFileReference varchar(1000) default NULL COMMENT 'URI of an external file, which may or may not be cached locally',
  ulf_PreferredSource enum('local','external','iiif','iiif_image','tiled') NOT NULL default 'local' COMMENT 'Preferred source of file if both local file and external reference set',
  ulf_Thumbnail blob COMMENT 'Cached autogenerated thumbnail for common image formats',
  ulf_Caption varchar(255) COMMENT 'A user-entered textual name of the file or image',
  ulf_Description text COMMENT 'A user-entered textual description of the file or image contents',
  ulf_Copyright varchar(255) COMMENT 'Copyright statement or a URI leading to a copyright statement. Consider using Creative Commons categories.',
  ulf_Copyowner varchar(255) COMMENT 'The owner of the copyright in the file ir image (person or organisation)',
  ulf_MimeExt varchar(10) default NULL COMMENT 'Extension of the file, used to look up in mimetype table',
  ulf_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Flag whether added by import = 1 or manual editing = 0',
  ulf_FileSizeKB int unsigned default NULL COMMENT 'File size in Kbytes calculated at upload',
  ulf_FilePath varchar(1024) default NULL COMMENT 'The path where the uploaded file is stored',
  ulf_FileName varchar(512) default NULL COMMENT 'The filename for the uploaded file',
  ulf_Parameters text COMMENT 'Parameters including source (flickr,youtube...), default player etc. used to determine special processing',
  ulf_WhoCanView enum('viewable','loginrequired') NULL COMMENT 'Defines if the file is visible when not logged in. If public or blank then file is visible to all',
  ulf_MD5Checksum text(32) NULL COMMENT 'A checksum for the uploaded file which can be used to verify integrity and to merge duplicates',
  PRIMARY KEY  (ulf_ID),
  KEY ulf_ObfuscatedFileIDKey (ulf_ObfuscatedFileID),
  KEY ulf_Description (ulf_Description(100)),
  KEY ulf_UploaderUGrpID (ulf_UploaderUGrpID),
  KEY ulf_MimeExt (ulf_MimeExt)
) ENGINE=InnoDB COMMENT='Index to uploaded files linked from records';

-- --------------------------------------------------------

--
-- Table structure for table 'sysArchive'
--

CREATE TABLE sysArchive (
  arc_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key of archive table',
  arc_Table enum('rec','cfn','crw','dtg','dty','fxm','ont','rst','rtg','rty','rcs','trm','trn','urp','vcb','dtl','rfw','rrc','snd','cmt','ulf','sys','lck','tlu','ugr','ugl','bkm','hyf','rtl','rre','rem','rbl','svs','tag','wprm','chunk','wrprm','woot') NOT NULL COMMENT 'Identification of the MySQL table in which a record is being modified',
  arc_PriKey int unsigned NOT NULL COMMENT 'Primary key of the MySQL record in the table being modified',
  arc_ChangedByUGrpID smallint unsigned NOT NULL COMMENT 'User who is logged in and modifying this data',
  arc_OwnerUGrpID smallint unsigned default NULL COMMENT 'Owner of the data being modified (if applicable eg. records, bookmarks, tags)',
  arc_RecID int unsigned default NULL COMMENT 'Heurist record id (if applicable, eg. for records, bookmarks, tag links)',
  arc_TimeOfChange timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Timestamp of the modification',
  arc_DataBeforeChange mediumblob COMMENT 'A representation of the data in the MySQL record before the mod, may be a diff',
  arc_ContentType enum('del','raw','zraw','diff','zdiff') NOT NULL default 'raw' COMMENT 'Format of the data stored, del=deleted, raw=text dump, Diff=delta, Z=zipped indicates ',
  PRIMARY KEY  (arc_ID),
  KEY arc_Table (arc_Table,arc_ChangedByUGrpID,arc_OwnerUGrpID,arc_RecID,arc_TimeOfChange)
) ENGINE=InnoDB COMMENT='An archive of all (or most) changes in the database to allow';


-- --------------------------------------------------------

--
-- Table structure for table 'sysWorkflowRules'
--

CREATE TABLE sysWorkflowRules  (
  swf_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key',
  swf_RecTypeID  smallint unsigned NOT NULL COMMENT 'Record type, foreign key to defRecTypes table',
  swf_Stage int unsigned NOT NULL default '0' COMMENT 'trm_ID from vocabulary "Workflow stage" 2-9453',
  swf_Order tinyint unsigned zerofill NOT NULL default '000' COMMENT 'Ordering of stage per record type',
  swf_StageRestrictedTo varchar(255) default NULL Comment 'Comma separated list of ugr_ID who are allowed to set workgroup stage to this value. Null = anyone',
  swf_SetOwnership smallint NULL default NULL COMMENT 'Workgroup to be set as the owner group, Null = No change, 0=everyone',
  swf_SetVisibility  varchar(255) default NULL COMMENT 'public=anyone, viewable=all logged in, hidden = private, hidden may be followed by comma separated list of ugr_ID that should be set to have view permission',
  swf_SendEmail  varchar(255) default NULL COMMENT 'Comma separated list of ugr_ID that will be emailed on stage change',
  swf_EmailList varchar(255) default NULL COMMENT 'Comma separated list of extra email address that will be emailed on stage change',
  swf_RecEmailField smallint NULL default NULL COMMET 'Field within the record structure that contains an email to also be emailed',
  swf_EmailText varchar(255) default NULL COMMENT 'Email body text to be sent on stage change, allows field value substitutions',
PRIMARY KEY  (swf_ID),
UNIQUE KEY swf_StageKey (swf_RecTypeID, swf_Stage)
) ENGINE=InnoDB COMMENT='Describes the rules to be applied when the value of the Workflow stage field is changed to this value';
-- --------------------------------------------------------

--
-- Table structure for table 'sysDashboard'
--

CREATE TABLE sysDashboard (
  dsh_ID tinyint unsigned NOT NULL auto_increment,
  dsh_Order smallint COMMENT 'Used to define the order in which the dashboard entries are shown',
  dsh_Label varchar(64) COMMENT 'The short text which will describe this function in the shortcuts',
  dsh_Description varchar(1024) COMMENT 'A longer text giving more information about this function to show as a description below the label or as a rollover',
  dsh_Enabled enum('y','n') NOT NULL default 'y' COMMENT 'Allows unused functions to be retained so they can be switched back on',
  dsh_ShowIfNoRecords enum('y','n') NOT NULL default 'y' COMMENT 'Deteremines whether the function will be shown on the dashboard if there are no records in the databar (eg. no point in showing searches if nothing to search)',
  dsh_CommandToRun varchar(64) COMMENT 'Name of commonly used functions',
  dsh_Parameters varchar(250) COMMENT 'Parameters to pass to the command eg the record type to create',
  PRIMARY KEY  (dsh_ID)
) ENGINE=InnoDB COMMENT='Defines an editable list of shortcuts to functions to be displayed on a popup dashboard at startup unless turned off';

-- --------------------------------------------------------

--
-- Table structure for table 'sysDocumentation'
--

CREATE TABLE sysDocumentation (
  doc_id tinyint unsigned NOT NULL auto_increment,
  doc_text text COMMENT 'Relevant documentation as text',
  PRIMARY KEY  (doc_id)
) ENGINE=InnoDB COMMENT='Descriptive information about this database and its function';

-- --------------------------------------------------------

--
-- Table structure for table 'sysIdentification'
--

CREATE TABLE sysIdentification (
  sys_ID tinyint unsigned NOT NULL default '1' COMMENT 'Only 1 record should exist in this table',
  sys_dbRegisteredID int unsigned NOT NULL default '0' COMMENT 'Allocated by Heurist master index, 0 indicates not yet registered',
  sys_dbVersion tinyint unsigned NOT NULL default '0' COMMENT 'Major version for the database structure',
  sys_dbSubVersion tinyint unsigned NOT NULL default '0' COMMENT 'Sub version',
  sys_dbSubSubVersion tinyint unsigned NOT NULL default '0' COMMENT 'Sub-sub version',
  sys_eMailImapServer varchar(100) default NULL COMMENT 'Email server intermediary for record creation via email',
  sys_eMailImapPort varchar(5) default NULL COMMENT 'port for imap email server',
  sys_eMailImapProtocol varchar(5) default NULL COMMENT 'protocol for imap email server',
  sys_eMailImapUsername varchar(50) default NULL COMMENT 'user name for imap email server',
  sys_eMailImapPassword varchar(40) default NULL COMMENT 'password for imap email server',
  sys_IncomingEmailAddresses varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses for archiving emails visible to all admins',
  sys_TargetEmailAddresses varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also ugr_TargetEmailAddresses',
  sys_UGrpsDatabase varchar(63) default NULL COMMENT 'Comma-delimited list of databases which share user credentials (synched on user addition). Databases must mutually specify one-another',
  sys_OwnerGroupID smallint unsigned NOT NULL default '1' COMMENT 'Workgroup which owns/administers this database, 1 by default',
  sys_dbName varchar(63) NOT NULL default 'Please enter a DB name ...' COMMENT 'A short descriptive display name for this database, distinct from the name in the URL',
  sys_dbOwner varchar(250) default NULL COMMENT 'Information on the owner of the database, may be a URL reference',
  sys_dbRights varchar(1000) NOT NULL default 'Please define ownership and rights here ...' COMMENT 'A statement of ownership and copyright for this database and content',
  sys_dbDescription varchar(1000) default NULL COMMENT 'A longer description of the content of this database',
  sys_SyncDefsWithDB varchar(1000) default NULL COMMENT 'One or more Zotero library name,userID,groupID,key combinations separated by pipe symbols, for synchronisation of Zotero libraries',
  sys_AutoIncludeFieldSetIDs varchar(63) default '0' COMMENT 'CSV list of fieldsets which are included in all rectypes',
  sys_RestrictAccessToOwnerGroup tinyint unsigned NOT NULL default '0' COMMENT 'If set, database may only be accessed by members of owners group',
  sys_URLCheckFlag tinyint unsigned NOT NULL default '0' COMMENT 'Flags whether system should send out requests to URLs to test for validity',
  sys_UploadDirectory varchar(128) default NULL COMMENT 'NOT USED: Absolute directory path for uploaded files (blank = use default from installation)',
  sys_hmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write hml representation of published records, default to hml within upload directory',
  sys_htmlOutputDirectory varchar(255) default NULL COMMENT 'Directory in which to write html representation of published records, default to html within upload directory',
  sys_NewRecOwnerGrpID smallint unsigned NOT NULL default '0' COMMENT 'Group which by default owns new records, 0=everyone. Allow override per user',
  sys_NewRecAccess enum('viewable','hidden','public','pending') NOT NULL default 'viewable' COMMENT 'Default visibility for new records - allow override per user',
  sys_SetPublicToPendingOnEdit tinyint unsigned NOT NULL default '0' COMMENT '0=immediate publish when ''public'' record edited, 1 = reset to ''pending''',
  sys_ConstraintDefaultBehavior enum('locktypetotype','unconstrainedbydefault','allownullwildcards') NOT NULL default 'locktypetotype' COMMENT 'Determines default behaviour when no detail types are specified',
  sys_AllowRegistration tinyint unsigned NOT NULL default '0' COMMENT 'If set, people can apply for registration through web-based form',
  sys_MediaFolders TEXT default NULL COMMENT 'Additional seimcolon-sep directories which can contain files indexed in database',
  sys_MediaExtensions varchar(1024) default 'jpg,jpe,jpeg,jfif,sid,png,gif,tif,tiff,bmp,rgb,doc,docx,odt,mp3,mp4,mpg,mpeg,mov,avi,wmv,wmz,aif,aiff,ashx,pdf,mbtiles,mid,midi,wms,wmd,qt,evo,cda,wav,csv,tsv,tab,txt,rtf,xml,xsl,xslx,xslt,xls,xlsx,hml,kml,kmz,shp,dbf,shx,svg,htm,html,xhtml,ppt,pptx,zip,gzip,tar,json,ecw,nxs,nxz,obj,3ds,stl,ply,gltf,glb,off,3dm,fbx,dae,wrl,3mf,ifc,brep,step,iges,fcstd,bim' COMMENT 'The file extensions to be harvested from the MediaFolders directories',
  sys_TreatAsPlaceRefForMapping VARCHAR(1000) DEFAULT '' COMMENT 'Comma delimited list of additional rectypes (local codes) to be considered as Places',
  sys_ExternalReferenceLookups TEXT default NULL COMMENT "Record type-function-field specifications for lookup to external reference sources such as GeoNames",  
  sys_NakalaKey varchar(250) default NULL COMMENT "Nakala API key. Retrieved from Nakala website",  
  PRIMARY KEY  (sys_ID)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='Identification/version for this Heurist database (single rec)';

-- --------------------------------------------------------

--
-- Table structure for table 'sysLocks'
--

CREATE TABLE sysLocks (
  lck_Action enum('buildcrosswalks','editdefinitions','errorscan','buildtempdb') NOT NULL COMMENT 'Type of action being carried out',
  lck_UGrpID smallint unsigned NOT NULL COMMENT 'Set to user ID to lock specified function',
  lck_Modified timestamp NOT NULL default CURRENT_TIMESTAMP COMMENT 'Date and time when the action was commenced, use to drop old locks',
  PRIMARY KEY  (lck_Action)
) ENGINE=InnoDB COMMENT='Provides token system for selected administrative actions';

-- --------------------------------------------------------

--
-- Table structure for table 'sysUGrps'
--

CREATE TABLE sysUGrps (
  ugr_ID smallint unsigned NOT NULL auto_increment COMMENT 'User or group ID, used wherever a user or group is to be identified',
  ugr_Type enum('user','workgroup','ugradclass') NOT NULL default 'user' COMMENT 'User or workgroup, special workgroup types also supported',
  ugr_Name varchar(63) NOT NULL COMMENT 'The unique user/login/group name, user name defaults to email address',
  ugr_LongName varchar(128) default NULL COMMENT 'An optional longer descriptive name for a Workgroup',
  ugr_Description varchar(1000) default NULL COMMENT 'Extended description of a Workgroup displayed on homepage',
  ugr_Password varchar(255) NOT NULL COMMENT 'Encrypted password string',
  ugr_eMail varchar(100) NOT NULL COMMENT 'Contact email address of the user/group',
  ugr_FirstName varchar(40) default NULL COMMENT 'Person''s first name, only for Users, not Workgroups',
  ugr_LastName varchar(63) default NULL COMMENT 'Person''s last name, only for Users, not Workgroups',
  ugr_Department varchar(120) default NULL,
  ugr_Organisation varchar(120) default NULL,
  ugr_City varchar(63) default NULL,
  ugr_State varchar(40) default NULL,
  ugr_Postcode varchar(20) default NULL,
  ugr_Interests varchar(255) default NULL COMMENT 'List of research interests, only for Users, not Workgroups',
  ugr_Enabled ENUM('y','n','y_no_add','y_no_delete','y_no_add_delete') NOT NULL default 'y' COMMENT 'Flags if user can use Heurist, normally needs authorising by admin',
  ugr_LastLoginTime datetime default NULL COMMENT 'Date and time of last login (but user may stay logged in)',
  ugr_MinHyperlinkWords tinyint unsigned NOT NULL default '3' COMMENT 'Filter hyperlink strings with less than this word count on hyperlink import ',
  ugr_LoginCount int unsigned NOT NULL default '0' COMMENT 'Number of times user haslogged in',
  ugr_IsModelUser tinyint unsigned NOT NULL default '0' COMMENT '1 indicates model user = domain profile',
  ugr_IncomingEmailAddresses varchar(4000) default NULL COMMENT 'Comma-sep list of incoming email addresses from which to archive emails',
  ugr_TargetEmailAddresses varchar(255) default NULL COMMENT 'Comma-sep list for selecting target for sending records as data, see also sys_TargetEmailAddresses',
  ugr_URLs varchar(2000) default NULL COMMENT 'URL(s) of group or personal website(s), comma separated',
  ugr_FlagJT tinyint unsigned NOT NULL default '0' COMMENT 'Flag to enable in Jobtrack/Worktrack application',
  ugr_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  ugr_NavigationTree MEDIUMTEXT default NULL COMMENT 'JSON array that describes treeview for filters',
  ugr_Preferences MEDIUMTEXT default NULL COMMENT 'JSON array with user preferences',
  usr_ExternalAuthentication varchar(1000) default NULL COMMENT 'JSON array with external authentication preferences',
  PRIMARY KEY  (ugr_ID),
  UNIQUE KEY ugr_Name (ugr_Name),
  UNIQUE KEY ugr_eMail (ugr_eMail)
) ENGINE=InnoDB  COMMENT='Users/Groups diff. by ugr_Type. May defer to similar table i';

-- --------------------------------------------------------

--
-- Table structure for table 'sysUsrGrpLinks'
--

CREATE TABLE sysUsrGrpLinks (
  ugl_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Primary key for user-to-group membership',
  ugl_UserID smallint unsigned NOT NULL COMMENT 'The user being assigned to a group',
  ugl_GroupID smallint unsigned NOT NULL COMMENT 'The group to which this user is being assigned',
  ugl_Role enum('admin','member') NOT NULL default 'member' COMMENT 'The role of this user in the group - member, admin',
  PRIMARY KEY  (ugl_ID),
  UNIQUE KEY ugl_CompositeKey (ugl_UserID,ugl_GroupID),
  KEY ugl_GroupID (ugl_GroupID)
) ENGINE=InnoDB COMMENT='Identifies groups to which a user belongs and their role in ';

-- --------------------------------------------------------

--
-- Table structure for table 'usrBookmarks'
--

CREATE TABLE usrBookmarks (
  bkm_ID int unsigned NOT NULL auto_increment COMMENT 'Primary key, ID for bookmarks',
  bkm_Added datetime NOT NULL COMMENT 'Date and time of addition of bookmark',
  bkm_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification',
  bkm_UGrpID smallint unsigned NOT NULL COMMENT 'Owner of the bookmark - all bookmarks must be owned',
  bkm_PwdReminder varchar(250) default NULL COMMENT 'Password reminder field or short notes about access',
  bkm_RecID int unsigned NOT NULL COMMENT 'The record to which this bookmark applies, must be set',
  bkm_Rating tinyint unsigned NOT NULL default '0' COMMENT 'Five point rating for interest/quality/content',
  bkm_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Set to 1 if bookmark added by import, 0 if added by data entry',
  bkm_ZoteroID int unsigned default NULL COMMENT 'Records your Zotero ID for this record for synchronisation with Zotero',
  bkm_Notes MEDIUMTEXT COMMENT 'Personal notes',
  PRIMARY KEY  (bkm_ID),
  UNIQUE KEY bkm_RecID (bkm_RecID,bkm_UGrpID),
  KEY bkm_UGrpID (bkm_UGrpID),
  KEY bkm_Modified (bkm_Modified)
) ENGINE=InnoDB COMMENT='Bookmark = personal data relating to a record, one for each ';

-- --------------------------------------------------------

--
-- Table structure for table 'usrHyperlinkFilters'
--

CREATE TABLE usrHyperlinkFilters (
  hyf_String varchar(63) NOT NULL COMMENT 'Hyperlink string to be ignored when encountered in hyperlink import',
  hyf_UGrpID smallint unsigned NOT NULL COMMENT 'User for which this string is to be ignored',
  UNIQUE KEY hyf_CompositeKey (hyf_String,hyf_UGrpID),
  KEY hyf_UGrpID (hyf_UGrpID)
) ENGINE=InnoDB COMMENT='Configure hyperlink import to ignore common link strings';

-- --------------------------------------------------------

--
-- Table structure for table 'usrRecTagLinks'
--

CREATE TABLE usrRecTagLinks (
  rtl_ID int unsigned NOT NULL auto_increment COMMENT 'Primary link table key, one tag linked to one record',
  rtl_TagID mediumint unsigned NOT NULL COMMENT 'The tag being linked to the record/bookmark',
  rtl_Order tinyint unsigned zerofill NOT NULL default '255' COMMENT 'Ordering of tags within the current record/bookmark',
  rtl_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT '0 indicates added while editing, 1 indicates added during import',
  rtl_RecID int unsigned NOT NULL COMMENT 'The record to which the tag is linked, should always be set',
  PRIMARY KEY  (rtl_ID),
  UNIQUE KEY rtl_composite_key (rtl_RecID,rtl_TagID),
  KEY rtl_TagIDKey (rtl_TagID)
) ENGINE=InnoDB COMMENT='Link table connecting tags to records';

-- --------------------------------------------------------

--
-- Table structure for table 'usrRecPermissions'
--
--     UNIQUE KEY rcp_composite_key (rcp_RecID,rcp_UGrpID)

CREATE TABLE IF NOT EXISTS usrRecPermissions (
    rcp_ID int unsigned NOT NULL auto_increment COMMENT 'Primary table key',
    rcp_UGrpID smallint unsigned NOT NULL COMMENT 'ID of group  to which an additional permission is being assigned',
    rcp_RecID int unsigned NOT NULL COMMENT 'The record to which this additional permission is assigned',
    rcp_Level enum('view','edit') NOT NULL default 'view' COMMENT 'Level of additional permission assigned',
    PRIMARY KEY  (rcp_ID)
) ENGINE=InnoDB COMMENT='Permissions for additional groups to view or edit a hidden (private) record';
            
-- --------------------------------------------------------

--
-- Table structure for table 'usrReminders'
--

CREATE TABLE usrReminders (
  rem_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Unique reminder ID',
  rem_RecID int unsigned NOT NULL COMMENT 'Record about which this reminder is sent, must refer to existing',
  rem_OwnerUGrpID smallint unsigned NOT NULL COMMENT 'Owner of the reminder, the person who created it',
  rem_ToWorkgroupID smallint unsigned default NULL COMMENT 'The workgroup to which the reminder should be sent',
  rem_ToUserID smallint unsigned default NULL COMMENT 'The individual user to whom the reminder should be sent',
  rem_ToEmail varchar(255) default NULL COMMENT 'The individual email address(es) to which the reminder should be sent',
  rem_Message varchar(1000) default NULL COMMENT 'The message to be attached to the reminder, optional',
  rem_StartDate date NOT NULL default '1970-01-01' COMMENT 'The first (or only) date for sending the reminder',
  rem_Freq enum('once','daily','weekly','monthly','annually') NOT NULL default 'once' COMMENT 'The frequency of sending reminders',
  rem_Nonce varchar(31) default NULL COMMENT 'Random number hash for reminders',
  rem_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (rem_ID),
  KEY rem_RecID (rem_RecID),
  KEY rem_OwnerUGrpID (rem_OwnerUGrpID),
  KEY rem_ToWorkgroupID (rem_ToWorkgroupID),
  KEY rem_ToUserID (rem_ToUserID)
) ENGINE=InnoDB COMMENT='Reminders attached to records and recipients, with start dat';

-- --------------------------------------------------------

--
-- Table structure for table 'usrRemindersBlockList'
--

CREATE TABLE usrRemindersBlockList (
  rbl_RemID mediumint unsigned NOT NULL COMMENT 'Reminder ID to be blocked',
  rbl_UGrpID smallint unsigned NOT NULL COMMENT 'User who does not wish to receive this reminder',
  UNIQUE KEY rbl_composite_key (rbl_RemID,rbl_UGrpID),
  KEY rbl_UGrpID (rbl_UGrpID)
) ENGINE=InnoDB COMMENT='Allows user to block resending of specific reminders to them';

-- --------------------------------------------------------

--
-- Table structure for table 'usrReportSchedule'
--

CREATE TABLE usrReportSchedule (
  rps_ID smallint unsigned NOT NULL auto_increment COMMENT 'Primary ID of a report output update specification',
  rps_Type enum('smarty') NOT NULL default 'smarty' COMMENT 'The type of report being generated',
  rps_Title varchar(64) NOT NULL COMMENT 'A title for this specification for use in selection menus',
  rps_FilePath varchar(128) default NULL COMMENT 'The full file path to which the report is to be generated, filestore/dbname/generated-reports if blank',
  rps_URL varchar(128) default NULL COMMENT 'The corresponding URL for web access to the directory in which this report is published, to , filestore/dbname/generated-reports if blank',
  rps_FileName varchar(64) NOT NULL COMMENT 'The base name of the report being published - wil lbe compelted with file types',
  rps_HQuery text NOT NULL COMMENT 'The Heurist query to be used in generating this report',
  rps_Template varchar(64) NOT NULL COMMENT 'The name of the template file to be used in generating this report',
  rps_IntervalMinutes int default NULL COMMENT 'The interval in minutes between regenerations of the report output, null = never',
  rps_Added timestamp NULL default NULL COMMENT 'The date when this specification was added',
  rps_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'The date this specification was last updated',
  PRIMARY KEY  (rps_ID),
  UNIQUE KEY rps_path_filename (rps_FilePath,rps_FileName)
) ENGINE=InnoDB COMMENT='Specs for writing out static files from a query plus templat';

-- --------------------------------------------------------

--
-- Table structure for table 'usrSavedSearches'
--

CREATE TABLE usrSavedSearches (
  svs_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Saved search ID, used in publishing, primary key',
  svs_Name varchar(128) NOT NULL COMMENT 'The display name for this saved search',
  svs_Added date NOT NULL default '1000-01-01' COMMENT 'Date and time saves search added',
  svs_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time saves search last modified',
  svs_Query text NOT NULL COMMENT 'The text of the saved search - added to search URL',
  svs_UGrpID smallint unsigned NOT NULL COMMENT 'The creator/owner or workgroup for the saved search',
  svs_ExclusiveXSL varchar(250) default '' COMMENT 'Name of XSL to which to lock this publish format, blank = any XSL OK',
  PRIMARY KEY  (svs_ID),
  KEY svs_UGrpID (svs_UGrpID)
) ENGINE=InnoDB COMMENT='Saved searches for personal/usergroup use and for publishing';

-- --------------------------------------------------------

--
-- Table structure for table 'usrTags'
--

CREATE TABLE usrTags (
  tag_ID mediumint unsigned NOT NULL auto_increment,
  tag_UGrpID smallint unsigned NOT NULL COMMENT 'User or workgroup id of tag owner',
  tag_Text varchar(63) NOT NULL COMMENT 'The value (text) of the tag provided by the user or workgroup administrator',
  tag_Description varchar(250) default NULL COMMENT 'Description of the concept to which this tag is attached, optional',
  tag_AddedByImport tinyint unsigned NOT NULL default '0' COMMENT 'Flag as to whether this tag was added by an import (1) or by editing (0)',
  tag_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT ' Date of last modification of this record, used to get last updated date for table',
  PRIMARY KEY  (tag_ID),
  UNIQUE KEY tag_composite_key (tag_Text,tag_UGrpID),
  KEY tag_UGrpID (tag_UGrpID),
  KEY tag_Text (tag_Text)
) ENGINE=InnoDB COMMENT='Personal and Workgroup tags (formerly keywords)';

-- --------------------------------------------------------

--
-- Table structure for table 'woot_ChunkPermissions'
--

CREATE TABLE woot_ChunkPermissions (
  wprm_ChunkID int NOT NULL COMMENT 'ID of chunk for which permission is specified, may be repeated',
  wprm_UGrpID smallint NOT NULL COMMENT 'User with specified right to this chunk',
  wprm_GroupID smallint NOT NULL COMMENT 'Workgroups with specified right to this chunk',
  wprm_Type enum('RW','RO') NOT NULL COMMENT 'Read-write or read-only permission for this chunk/user/wg',
  wprm_CreatorID smallint NOT NULL COMMENT 'Creator of the permission (= user ID ???? <check>)',
  wprm_Created datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY wprm_chunk_composite_key (wprm_ChunkID,wprm_UGrpID,wprm_GroupID)
) ENGINE=InnoDB COMMENT='Permissions value for individual woot chunks';

-- --------------------------------------------------------

--
-- Table structure for table 'woot_Chunks'
--

CREATE TABLE woot_Chunks (
  chunk_ID int NOT NULL auto_increment COMMENT 'Primary ID for a version of the text chunks making up a woot entry (page)',
  chunk_WootID int NOT NULL COMMENT 'The ID of the woot entry (page) to which this chunk belongs',
  chunk_InsertOrder int NOT NULL COMMENT 'Order of chunk within woot.',
  chunk_Version int NOT NULL COMMENT 'A version code for the chunk, incremented when edited',
  chunk_IsLatest tinyint NOT NULL COMMENT 'Presumably flags whether this is the latest version of the chunk',
  chunk_DisplayOrder int NOT NULL COMMENT 'The order number of the chunk within the woot entry (page)',
  chunk_Text text COMMENT 'The actual XHTML content of the chunk',
  chunk_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the chunk',
  chunk_OwnerID int default NULL COMMENT 'Owner/creator (user ID) of the chunk',
  chunk_Deleted tinyint NOT NULL COMMENT 'Deletion marker for this chunk',
  chunk_EditorID int NOT NULL COMMENT 'Editor (user ID) of the chunk - presumably the last person to edit',
  PRIMARY KEY  (chunk_ID),
  UNIQUE KEY chunk_composite_key (chunk_WootID,chunk_InsertOrder,chunk_Version),
  KEY chunk_is_latest_key (chunk_IsLatest)
) ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Table structure for table 'woot_RecPermissions'
--

CREATE TABLE woot_RecPermissions (
  wrprm_WootID int NOT NULL COMMENT 'ID of the woot entry to which this permission applies, may be repeated',
  wrprm_UGrpID int NOT NULL COMMENT 'User ID to which this permission is being granted',
  wrprm_GroupID int NOT NULL COMMENT 'Workgroup ID to which this permission is being granted',
  wrprm_Type enum('RW','RO') NOT NULL COMMENT 'Type of permission being granted - read only or read-write',
  wrprm_CreatorID int NOT NULL COMMENT 'Creator of the permission',
  wrprm_Created datetime NOT NULL COMMENT 'Date and time of creation of the permission',
  UNIQUE KEY wrprm_composite_key (wrprm_WootID,wrprm_UGrpID,wrprm_GroupID)
) ENGINE=InnoDB COMMENT='Overall permissions for the woot record (entry/page)';

-- --------------------------------------------------------

--
-- Table structure for table 'woots'
--

CREATE TABLE woots (
  woot_ID int NOT NULL auto_increment COMMENT 'Primary ID of a woot record/entry/page',
  woot_Title varchar(8191) default NULL COMMENT 'Name of the woot page, unique identifier of the woot page',
  woot_Created datetime default NULL COMMENT 'Date and time of creation of the woot record/entry/page',
  woot_Modified timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP COMMENT 'Date and time of modification of the woot record/entry/page',
  woot_Version int NOT NULL COMMENT 'Version of the woot record/entry/page, presumably incremented on edit',
  woot_CreatorID int default NULL COMMENT 'Creator (user id) of the woot',
  PRIMARY KEY  (woot_ID),
  UNIQUE KEY woot_title_key (woot_Title(200))
) ENGINE=InnoDB COMMENT='Woot records (entries, pages) are linked to a set of XHTML c';
                                                               
-- ------------------------------------------------------------------------------------------------

--
-- Table structure for table 'record subsets'
--

CREATE TABLE usrWorkingSubsets (
  wss_ID mediumint unsigned NOT NULL auto_increment COMMENT 'Unique ID for the working subsets table',
  wss_RecID int unsigned NOT NULL COMMENT 'ID of a Record to be included in the working subset for a specific user',
  wss_OwnerUGrpID smallint unsigned NOT NULL COMMENT 'Person to whose working subset this Record ID is assigned',
  PRIMARY KEY  (wss_ID),
  KEY wss_RecID (wss_RecID),
  KEY wss_OwnerUGrpID (wss_OwnerUGrpID)
) ENGINE=InnoDB COMMENT='Lists a set of Records to be included in a working subset for a user. Working susbset is an initial filter on all filter actions.';


-- ------------------------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------------------------

-- STANDARD DATA FOR A NEW DATABASE
-- This is run BEFORE triggers and referential constraints

-- *** Update the following INSERT statement if database version # is changed

-- Insert a single row in sysIdentification table = the DB identification record
 DELETE FROM sysIdentification where sys_ID=1;
 INSERT INTO sysIdentification(sys_ID,sys_dbRegisteredID,sys_dbVersion,sys_dbSubVersion,
  sys_dbSubSubVersion,sys_eMailImapServer,sys_eMailImapPort,
  sys_eMailImapProtocol,sys_eMailImapUsername,sys_eMailImapPassword,
  sys_UGrpsdatabase,sys_OwnerGroupID,sys_ConstraintDefaultBehavior,sys_MediaFolders)
  VALUES (1,0,1,3,18,NULL,NULL,NULL,NULL,NULL,NULL,1,'locktypetotype','uploaded_files');

  -- Note: database sub version updated manually to '1' at 6pm 22/8/12
  -- 0 is everyone, 1 is the owning admins group, 2 is default dbAdmin user

-- The owners of the database - always group 1
INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Description,ugr_Type,ugr_Password,ugr_eMail,ugr_Enabled,ugr_FirstName,ugr_LastName)
 VALUES (1,'Database Managers',
 'Group 1 owns databases by default. DO NOT DELETE.',
 'This workgroup contains the administrators of the database. The first user (user #2) has special status as the master user. They cannot be deleted',
 'Workgroup','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=1','y','db','owners');
-- Note: ugr_id=1 is set as the database owner group in the sysidentification table

-- This user (#2) is replaced with the user who created the database
 INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail,ugr_Enabled,ugr_FirstName,ugr_LastName)
 VALUES (2,'dbAdmin','User 2 is default administrator for databases. DO NOT DELETE',
 'User','TO BE RESET','EMAIL NOT SET FOR ID=2','y','sys','admin');
-- password is 'none'
-- Note: ugr_id=2 is set as the database admin in the sysUsrGrpLinks table
-- there can be multipl admins for a database

-- An extra group of users to give people the idea
 INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail,ugr_Enabled,ugr_FirstName,ugr_LastName)
 VALUES (3,'Other users',
 'Another group, can be used eg. for guests',
 'Workgroup','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=3','y','other','users');
-- Note: ugr_id=1 is set as the database owner group in the sysidentification table

-- insert as 4 then change - cannot insert 0 straight up, as it gets reset to 4 in any case
 INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail,ugr_Enabled,ugr_FirstName,ugr_LastName)
 VALUES (4,'Everyone',
 'Group 0 represents all logged in users. DO NOT DELETE.',
 'Workgroup','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=0','y','every','user');
-- Note: ugr_id=0 is set as the default new rec owner in the sysidentification table, this entry is require to constraint
 UPDATE sysUGrps set ugr_ID = 0 where ugr_ID = 4;
-- cannot insert 0 straight up, it gets appended

-- An extra group of users to give people the idea
 INSERT INTO sysUGrps (ugr_ID,ugr_Name,ugr_LongName,ugr_Type,ugr_Password,ugr_eMail,ugr_Enabled,ugr_FirstName,ugr_LastName)
 VALUES (4,'Website filters',
 'Filters for building a website',
 'Workgroup','PASSWORD NOT REQUIRED','EMAIL NOT SET FOR ID=5','y','other','users');



-- Insert a row to define the link between group 1 (dbowners), group 3 (other users) and group 5 (website filters) and user 2 (the first admin)
INSERT IGNORE INTO sysUsrGrpLinks (ugl_UserID,ugl_GroupID,ugl_Role) VALUES (2,1,'admin');
INSERT IGNORE INTO sysUsrGrpLinks (ugl_UserID,ugl_GroupID,ugl_Role) VALUES (2,3,'admin');
INSERT IGNORE INTO sysUsrGrpLinks (ugl_UserID,ugl_GroupID,ugl_Role) VALUES (2,4,'admin');


-- defRectypegroups and defDetailtypeGroups are now inserted by coreDefinitions.txt


-- Note: language terms removed because of duplicate key error because they occur in the core definitions
-- auto increment values for tables also removed because the concept code system takes care of core
-- definitions. defOntologies and FileExtToMimetype table now populated by coreDefinitions.txt

-- ------------------------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------------------------
-- ------------------------------------------------------------------------------------------------
COMMIT;
SET FOREIGN_KEY_CHECKS= 1;
SET UNIQUE_CHECKS = 1;
