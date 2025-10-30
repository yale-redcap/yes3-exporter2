# YES3 Exporter II README

Version 2.0.15, October 2025

> YES3 Exporter II is documented online here: https://yale-redcap.github.io/yes3-exporter2-docs/  
> We will update this documentation as needed, so you should check back periodically.

The YES3 Exporter II complements REDCap's reports and data export tool by adding functionality to support integration with statistical software and datamarts.

Perhaps the most important feature is support for "horizontal layouts", that is exported datasheets for which there is one column per event-field combination. 

Other features include:

- An expanded documentation model: our goal is to put all the information you need to operate Exporter II into the User Interface (UI). Documentation tools include:
    - Dynamic tooltips on every interactive UI element (buttons, textboxes etc).
    - Detailed topic-specific popups that can remain open and be resized and moved around as you work.
    - Online technical documentation.
- Support for comma-delimited (csv) or tab-delimited (tsv) export datasheets.
- Options for coding and conditioning data values:
    - Remove PHI, freetext and/or date fields from the export, similar to REDCap's reporting options.
    - Perform REDCap-compatible date shifting and record hashing (validated as of REDCap v15.5).
    - Convert of exported data to pure ASCII.
    - Drop non-printable characters (newlines and tabs are converted to spaces).
- Export-specific data dictionaries (csv files) that include data distribution summaries for each exported column. Depending on the field type, the summary will include:
     - Univariate calculations: n, mean, min, max, mean, sample standard deviation, sum of values, sum of squared values. 
     - For date and date/time fields, formatted mean, min and max are also provided.
     - Frequency table (JSON structure; nominal fields only).
     - Maximum observed string length (for text fields). This is intended for optimizing analysis files (SAS, R, SPSS etc).
- The ability to organize export items by form and/or event.
- Rigorous auditing of every export, optionally with daily summaries emailed to a designated user.
- The ability to roll back an export specification to any previous version 
- The export payload can include execution-ready SAS code to build datasets and format libraries. The code is optimized for the specific export. For example, character variable lengths are tuned to the observed max lengths in the export, potentially saving significant storage space. The dataset-building program will not replace an existing dataset if any error is encountered.

## YES3 Exporter II vs the original YES3 Exporter

YES3 Exporter II and the original YES3 Exporter are different EMs. You can have both enabled at the same time. We did not want to force you to immediately upgrade to Exporter II if you already had a major workflow investment in the original, but you should migrate from the original YES3 Exporter to YES3 Exporter II as time permits (it's easy, see the online documentation). We will not be supporting the original YES3 Exporter going forward.