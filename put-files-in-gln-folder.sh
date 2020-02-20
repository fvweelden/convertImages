#!/bin/bash
# shellcheck disable=SC2164 disable=SC2128
SOURCE=${2-/home/doncarlos/xxtract/source};
DEST=${3-/home/doncarlos/xxtract/dest};
LIST=$1;
PATTERN="[0-9]{13}";

echo "Xxtract put-files-in-gln-folder.sh starting ...";
echo "SOURCE: $SOURCE";
echo "DEST: $DEST";
echo "LIST: $LIST";
echo "PATTERN: $PATTERN";

# check for list.txt file and process contents
if [[ -f "$LIST" ]]; then
	echo "Found $LIST";
	dos2unix "$LIST";

	while IFS= read -r filename; do
		target_found=0;
		readarray -t -d '' found_files < <(find $SOURCE -type f -name "$filename" -print0);

		for ((i = 0; i < ${#found_files[@]}; i++)) do
			if [[ "${found_files[i]}" != *([[:blank:]]) ]]; then
				echo "Found file ${found_files[i]}";

				gln=( $(echo ${found_files[i]}|grep -Eo $PATTERN|head -1 ) );

				if [[ "$gln" != *([[:blank:]]) ]]; then
					echo "Found GLN $gln";

					if [[ ! -d "$DEST/$gln" ]]; then
						echo "Creating $DEST/$gln";
						mkdir "$DEST/$gln";
					fi;

					if [[ -f "$DEST/$gln/$filename" && "${found_files[i]}" -ot "$DEST/$gln/$filename" ]]; then
						echo "Copying newer ${found_files[i]} to $DEST/$gln"; 
						cp "${found_files[i]}" "$DEST/$gln"; 
					else
						if [[ ! -f "$DEST/$gln/$filename" ]]; then
							echo "Copying ${found_files[i]} to $DEST/$gln"; 
							cp "${found_files[i]}" "$DEST/$gln";
						else
							echo "Not copying older ${found_files[i]}";
						fi;
					fi;
				else
					echo "No GLN in path";

					if [[ ! -d "$DEST/0000000000000" ]]; then
						echo "Creating $DEST/0000000000000";
						mkdir "$DEST/0000000000000";
					fi;

					echo "Copying ${found_files[i]} to $DEST/0000000000000"; 
					cp "${found_files[i]}" "$DEST/0000000000000";
				fi;

			else
				echo "$filename not found.";
			fi;
		done;

	done < $LIST;
else
	echo "$LIST not found.";
fi;

