<?php
function sheet_updated($apiKey, $spreadsheetId, $sheetName, $cellRange, $lastUpdateFile) {
    // Fetch value from Google Sheets
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$sheetName}!{$cellRange}?key={$apiKey}";

    // Initialize cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Send the request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        return false;
    }

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    $data = json_decode($response, true);

    // Extract current value
    $currentValue = isset($data['values'][0][0]) ? $data['values'][0][0] : null;

    // If no value retrieved, return false
    if ($currentValue === null) {
        return false;
    }

    // Check if the file exists
    if (!file_exists($lastUpdateFile)) {
        // File doesn't exist, create it and save the value
        file_put_contents($lastUpdateFile, $currentValue);
        return false;
    }

    // Read saved value
    $savedValue = file_get_contents($lastUpdateFile);

    // Compare values
    if ($savedValue === $currentValue) {
        return true;
    }

    // Values are different, update the file
    file_put_contents($lastUpdateFile, $currentValue);
    return false;
}
function update_json($apiKey, $spreadsheetId, $sheetName, $outputFile) {
        // Construct API URL
        $inputJson = "https://sheets.googleapis.com/v4/spreadsheets/$spreadsheetId/values/$sheetName?key=$apiKey";
        
        // Fetch and decode the data
        $response = file_get_contents($inputJson);        
        $inputData = json_decode($response, true);


        // Extract headers, display flags, and data rows
        $headers = $inputData['values'][0];
        $displays = $inputData['values'][1];
        $rows = array_slice($inputData['values'], 2);

        // Transform data to JSON array of objects
        $result = "";
        $result .="[";
        for ($i = 0; $i < count($rows); $i++) {
            if ($i > 0) $result .= ",";
            $result .= "{";
            
            $first = true;
            for ($j = 0; $j < count($headers); $j++) {
                if ($displays[$j] !== "0") {
                    if (!$first) $result .= ",";
                    $result .= '"' . $headers[$j] . '":"' . $rows[$i][$j] . '"';
                    $first = false;
                }
            }
            
            $result .= "}";
        }
        $result .= "]";

 
        // Save to file
        file_put_contents($outputFile, $result);

        return $result;
}
function jsonToGroupedData($jsonFilePath, $stu_id) {
    // Read and decode JSON file
    $jsonData = file_get_contents($jsonFilePath);
    $data = json_decode($jsonData, true);
    
    // If data is empty or invalid, return null
    if (!$data || empty($data)) {
        return null;
    }

    // Case 1: Return all students basic info (stu_id=0)
    if ($stu_id === '0') {
        $result = array();
        // Skip header (index 0) and process all student entries
        for ($i = 1; $i < count($data); $i++) {
            $result[] = array(
                'stu_id' => $data[$i]['stu_id'],
                'FullName' => $data[$i]['FullName'],
                'Syndicate' => $data[$i]['Syndicate']
            );
        }
        $finalResult = [$result]; // Wrap in array to match format
        
        // Save to stu_id.json
        file_put_contents('stu_id.json', json_encode($finalResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        return $finalResult;
    }
    
    // Case 2: Return grouped data for specific student
    // Initialize result groups
    $result = array(
        array(), // Group 1: 1-199 and non-numeric
        array(), // Group 2: 200-299
        array()  // Group 3: 300+
    );
    
    // Get labels from first element
    $labels = $data[0];
    
    // Find student data
    $studentData = null;
    foreach (array_slice($data, 1) as $row) {
        if ($row['stu_id'] === $stu_id) {
            $studentData = $row;
            break;
        }
    }
    
    // Return empty groups if student not found
    if (!$studentData) {
        return $result;
    }
    
    // Process each field
    foreach ($labels as $key => $label) {
        // Skip if no value exists
        if (!isset($studentData[$key])) {
            continue;
        }
        
        $value = $studentData[$key];
        $data_pair = array($label => $value);
        
        // Group based on key
        if (is_numeric($key)) {
            $numKey = intval($key);
            if ($numKey <= 199) {
                $result[0] = array_merge($result[0], $data_pair);
            } elseif ($numKey <= 299) {
                $result[1] = array_merge($result[1], $data_pair);
            } else {
                $result[2] = array_merge($result[2], $data_pair);
            }
        } else {
            // Non-numeric keys go to first group
            $result[0] = array_merge($result[0], $data_pair);
        }
    }
    
    // Save to {student_id}.json
    //file_put_contents($stu_id . '.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return $result;
}

// Usage example:
header('Content-Type: application/json; charset=utf-8');



// Configuration
$apiKey = 'AIzaSyBzbPw2I0pqljbPfZERB_-peWTJNQHFR1o';
$spreadsheetId = '1C58zCYzJf7WYq_17MKzdse6MKf6rpfoZftvtlDm24CY';
$sheetName = 'student';
$lastUpdateFile = 'lastupdate.txt';
$cellRange = 'A1';
$student_id = $_GET['stu_id'];
// Using the function with the configuration
if (sheet_updated($apiKey, $spreadsheetId, $sheetName, $cellRange, $lastUpdateFile)) {

} else {
    // If false, redirect to stu_profile.php
    $datas = update_json($apiKey, $spreadsheetId, $sheetName, 'student.json');
    jsonToGroupedData('student.json','0');
}
//$tables = student_profile('student.json',$student_id);
$res = jsonToGroupedData('student.json',$student_id);
echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
//file_put_contents($student_id.'.json', json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));


?>
