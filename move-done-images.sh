#!/bin/bash
# shellcheck disable=SC2164 disable=SC2128
oLang=$LANG;
oLcAll=$LC_ALL;

# check if these environment variables have been previously set
# this is useful for local testing
SOURCE=/media/picture_share/workflow/_sendtoxxtract;
DEST_SERVER=VANILLE;
DEST_PATH="/home/localadmin/$DEST_SERVER";
LINK_TEST=.vanille;

echo "Xxtract move-done-images.sh starting ...";
echo "SOURCE: $SOURCE";
echo "DEST_SERVER: $DEST_SERVER";
echo "DEST_PATH: $DEST_PATH";
echo "LINK_TEST: $LINK_TEST";

while true; do
	echo "Checking Link status ...";
	cd $DEST_PATH;
	mapfile -t link < <(find . -maxdepth 1 -type f -name "$LINK_TEST");
	
	if [[ "$link" != *([[:blank:]]) ]]; then
		echo "Checking for GLNs ...";
		cd $SOURCE;
		glns=(*);

		# process files

		if [[ "$glns" != "*" ]]; then
			finished=0;

			until (( finished == 1 )); do
				echo "Processing ${#glns[*]} folders ...";

				for gln in "${glns[@]}"; do

					if [[ "$gln" != *([[:blank:]]) && "$gln" != *"."* ]]; then

						if [[ ${#gln} != 13 ]]; then

							if [[ ${#gln} -gt 13 && "${gln:$((${#gln}-1))}" != "H" ]]; then
								echo "Folder name $gln is malformed.  Appending -CHECK-GLN-LENGTH to folder name";
								mv "$SOURCE/$gln" "$SOURCE/$gln"-CHECK-GLN-LENGTH;
							fi;
						else
							echo "Checking $gln for files ...";
							cd "$SOURCE/$gln";
							mediaFiles=(*);

							if [[ "${mediaFiles[0]}" != "*" ]]; then

								if [ ! -d "$DEST_PATH/$gln" ]; then
										echo "Creating $DEST_PATH/$gln/upload folder ...";
										mkdir "$DEST_PATH/$gln";
										mkdir "$DEST_PATH/$gln/upload";
								fi;

								echo "Processing ${#mediaFiles[@]} files ...";

								for mediaFile in "${mediaFiles[@]}"; do
									test=$(stat "$mediaFile"|grep directory);

									if [[ "$mediaFile" != *([[:blank:]]) && "$test" == *([[:blank:]]) && "$mediaFile" != "Thumbs.db" ]]; then
										echo "mv $SOURCE/$gln/$mediaFile $DEST_PATH/$gln/upload/$mediaFile";
										mv "$SOURCE/$gln/$mediaFile" "$DEST_PATH/$gln/upload/$mediaFile" > /dev/null 2>&1;
									elif [[ "$test" != *([[:blank:]]) || "$mediaFile" == "Thumbs.db" ]]; then
										cd $SOURCE;
										echo "rm -fr $SOURCE/$gln/$mediaFile";
										rm -fr "${SOURCE:?}/$gln/$mediaFile";
									fi;
								done;

								echo "Done processing files for $gln";
								cd $SOURCE;
							fi;
						fi;
					fi;

				done;
				
				finished=1;
				echo "Checking for new files ...";
				cd $SOURCE;
				glns=(*);

				for gln in "${glns[@]}"; do
					if [[ "$gln" != *"."* ]]; then
						cd "$SOURCE/$gln";
						mediaFiles=(*);

						if [[ "${mediaFiles[0]}" == "*" ]]; then
							cd $SOURCE;
							echo "rm -fr $gln";
							rm -fr "$gln";
						else
							finished=0;
						fi;

						cd $SOURCE;
					fi;
				done;

				if [[ $finished == 0 ]]; then
					glns=(*);
				else
					echo "No new files found";
				fi;
			done;
		else
			echo "No GLNs found";
		fi;
	else
		echo "Link to $DEST_SERVER is not up.";
	fi;

	LANG=$oLang;
	LC_ALL=$oLcAll;
	sleep 60;
done;