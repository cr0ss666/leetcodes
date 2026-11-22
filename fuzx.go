package main

import (
	"bufio"
	"crypto/tls"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"os"
	"sort"
	"strconv"
	"strings"
	"sync"
	"sync/atomic"
	"time"
)

// ─── ANSI color codes ───────────────────────────────────────────────
const (
	red       = "\033[91m"
	secondary = "\033[90m"
	primary   = "\033[92m"
	rest      = "\033[0m"
)

// ─── Custom flag types for repeatable arguments ────────────────────
type stringSliceFlag []string

func (s *stringSliceFlag) String() string { return strings.Join(*s, ", ") }
func (s *stringSliceFlag) Set(value string) error {
	*s = append(*s, value)
	return nil
}

type intSliceFlag []int

func (i *intSliceFlag) String() string { return fmt.Sprint(*i) }
func (i *intSliceFlag) Set(value string) error {
	v, err := strconv.Atoi(value)
	if err != nil {
		return fmt.Errorf("invalid integer: %s", value)
	}
	*i = append(*i, v)
	return nil
}

// ─── Task / Result structs ─────────────────────────────────────────
type task struct {
	id       int
	payloads map[string]string
	url      string
	data     string
}

type result struct {
	task       task
	statusCode int
	bodySize   int
	body       string
	err        error
	duration   time.Duration
}

// ─── Custom cookie jar that records cookies ────────────────────────
type recordableJar struct {
	jar     *cookiejar.Jar
	mu      sync.Mutex
	cookies []*http.Cookie
}

func newRecordableJar() *recordableJar {
	jar, _ := cookiejar.New(nil)
	return &recordableJar{jar: jar}
}

func (r *recordableJar) SetCookies(u *url.URL, cookies []*http.Cookie) {
	r.mu.Lock()
	defer r.mu.Unlock()
	r.jar.SetCookies(u, cookies)
	r.cookies = append(r.cookies, cookies...)
}

func (r *recordableJar) Cookies(u *url.URL) []*http.Cookie {
	r.mu.Lock()
	defer r.mu.Unlock()
	return r.jar.Cookies(u)
}

type serialisableCookie struct {
	Name     string `json:"name"`
	Value    string `json:"value"`
	Path     string `json:"path"`
	Domain   string `json:"domain"`
	Expires  string `json:"expires"`
	Secure   bool   `json:"secure"`
	HTTPOnly bool   `json:"http_only"`
}

func (r *recordableJar) SaveToFile(filePath string) error {
	r.mu.Lock()
	defer r.mu.Unlock()

	seen := make(map[string]*http.Cookie)
	for _, c := range r.cookies {
		key := c.Name + "|" + c.Domain + "|" + c.Path
		seen[key] = c
	}

	var sc []serialisableCookie
	for _, c := range seen {
		exp := ""
		if !c.Expires.IsZero() {
			exp = c.Expires.Format(time.RFC3339)
		}
		sc = append(sc, serialisableCookie{
			Name:     c.Name,
			Value:    c.Value,
			Path:     c.Path,
			Domain:   c.Domain,
			Expires:  exp,
			Secure:   c.Secure,
			HTTPOnly: c.HttpOnly,
		})
	}

	f, err := os.Create(filePath)
	if err != nil {
		return err
	}
	defer f.Close()

	enc := json.NewEncoder(f)
	enc.SetIndent("", "  ")
	return enc.Encode(sc)
}

// ─── URL extraction helper ────────────────────────────────────────
func urlExtract(paths string) error {
	var inPath, outPath string
	if strings.Contains(paths, ":") {
		parts := strings.SplitN(paths, ":", 2)
		inPath = strings.TrimSpace(parts[0])
		outPath = strings.TrimSpace(parts[1])
	} else {
		inPath = strings.TrimSpace(paths)
		outPath = inPath
	}

	f, err := os.Open(inPath)
	if err != nil {
		return fmt.Errorf("cannot open input file: %w", err)
	}
	defer f.Close()

	var urls []string
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := scanner.Text()
		if strings.Contains(line, "url:") {
			fields := strings.Fields(line)
			if len(fields) >= 2 {
				urls = append(urls, fields[1])
			}
		}
	}
	if err := scanner.Err(); err != nil {
		return err
	}

	out, err := os.Create(outPath)
	if err != nil {
		return fmt.Errorf("cannot create output file: %w", err)
	}
	defer out.Close()

	for _, u := range urls {
		fmt.Fprintln(out, u)
	}
	fmt.Printf("%s[%s✓%s]%s Extracted %s%d%s URLs to %s\n",
		secondary, primary, secondary, rest,
		primary, len(urls), rest, outPath)
	return nil
}

// ─── Wordlist reader ───────────────────────────────────────────────
func readWordlist(path string) ([]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	var lines []string
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line != "" {
			lines = append(lines, line)
		}
	}
	return lines, scanner.Err()
}

// ─── Cartesian product ─────────────────────────────────────────────
func cartesianProduct(wordlists map[string][]string) []map[string]string {
	keys := make([]string, 0, len(wordlists))
	for k := range wordlists {
		keys = append(keys, k)
	}
	sort.Strings(keys)

	if len(keys) == 0 {
		return []map[string]string{{}}
	}

	result := []map[string]string{{}}
	for _, key := range keys {
		values := wordlists[key]
		var next []map[string]string
		for _, combo := range result {
			for _, val := range values {
				newCombo := make(map[string]string, len(combo)+1)
				for k, v := range combo {
					newCombo[k] = v
				}
				newCombo[key] = val
				next = append(next, newCombo)
			}
		}
		result = next
	}
	return result
}

// ─── Print banner ──────────────────────────────────────────────────
func printBanner(targetURL string, wordlists map[string][]string, conditions map[string]interface{}, threads int, method string) {
	keys := make([]string, 0, len(wordlists))
	for k := range wordlists {
		keys = append(keys, k)
	}

	fmt.Printf("\n%s[%s•%s]%s URL%s:%s %s%s\n",
		secondary, rest, secondary, primary, secondary, primary, targetURL, rest)
	fmt.Printf("%s[%s•%s]%s Method%s:%s %s%s\n",
		secondary, rest, secondary, primary, secondary, primary, method, rest)
	fmt.Printf("%s[%s•%s]%s Threads%s:%s %d%s\n",
		secondary, rest, secondary, primary, secondary, primary, threads, rest)
	fmt.Printf("%s[%s•%s]%s Args%s:%s %s%s\n",
		secondary, rest, secondary, primary, secondary, primary, strings.Join(keys, ", "), rest)
	fmt.Printf("%s[%s•%s]%s Conditions%s:%s %v%s\n",
		secondary, rest, secondary, primary, secondary, primary, conditions, rest)
	fmt.Println(primary, strings.Repeat("─", 50), rest)
}

// ─── Validate HTTP method ─────────────────────────────────────────
func validMethod(m string) bool {
	switch strings.ToUpper(m) {
	case "GET", "POST", "PUT", "DELETE", "PATCH", "HEAD", "OPTIONS", "CONNECT", "TRACE":
		return true
	}
	return false
}

// ─── Main ─────────────────────────────────────────────────────────
func main() {
	var (
		urlFlag         string
		wordlistFlag    stringSliceFlag
		headerFlag      stringSliceFlag
		dataFlag        string
		cookieFlag      string
		cookieJarFlag   string
		matchStatusFlag intSliceFlag
		matchSizeFlag   int
		matchStringFlag string
		invMatchStrFlag string
		threadsFlag     int
		outFlag         string
		extractFlag     string
		noErrorsFlag    bool
		quiteFlag       bool
		methodFlag      string
	)

	flag.Var(&wordlistFlag, "w", "Wordlist file:KEY (repeatable)")
	flag.Var(&wordlistFlag, "wordlist", "Wordlist file:KEY (repeatable)")
	flag.Var(&headerFlag, "H", "Header Key:Value (repeatable)")
	flag.Var(&headerFlag, "header", "Header Key:Value (repeatable)")
	flag.Var(&matchStatusFlag, "mc", "Match status code (repeatable)")
	flag.Var(&matchStatusFlag, "match-status", "Match status code (repeatable)")

	flag.StringVar(&urlFlag, "u", "", "Target URL")
	flag.StringVar(&urlFlag, "url", "", "Target URL")
	flag.StringVar(&dataFlag, "d", "", "POST/PUT body data")
	flag.StringVar(&dataFlag, "data", "", "POST/PUT body data")
	flag.StringVar(&cookieFlag, "cookie", "", "Cookie header value")
	flag.StringVar(&cookieJarFlag, "cookie-jar", "", "File to save cookies to")
	flag.IntVar(&matchSizeFlag, "ms", 0, "Minimum response size")
	flag.IntVar(&matchSizeFlag, "match-size", 0, "Minimum response size")
	flag.StringVar(&matchStringFlag, "st", "", "String that must be present in response")
	flag.StringVar(&matchStringFlag, "match-string", "", "String that must be present in response")
	flag.StringVar(&invMatchStrFlag, "ist", "", "Inverted match: string that must NOT be present")
	flag.StringVar(&invMatchStrFlag, "inverted-match-string", "", "Inverted match: string that must NOT be present")
	flag.IntVar(&threadsFlag, "t", 10, "Number of concurrent threads")
	flag.IntVar(&threadsFlag, "threads", 10, "Number of concurrent threads")
	flag.StringVar(&outFlag, "o", "f_result.txt", "Output file for matches")
	flag.StringVar(&outFlag, "out", "f_result.txt", "Output file for matches")
	flag.StringVar(&extractFlag, "e", "", "Extract URLs from file (-e input:output or -e input)")
	flag.StringVar(&extractFlag, "extract", "", "Extract URLs from file (-e input:output or -e input)")
	flag.BoolVar(&noErrorsFlag, "no-errors", false, "Suppress error outputs")
	flag.BoolVar(&quiteFlag, "quite", false, "Suppress non-match outputs")
	flag.StringVar(&methodFlag, "method", "POST", "HTTP method (GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS, CONNECT, TRACE)")
	flag.StringVar(&methodFlag, "X", "POST", "HTTP method (shorthand for --method)")

	flag.Usage = func() {
		fmt.Fprintf(os.Stderr, "FUZX - Minimal Web Fuzzer (Go)\n\n")
		fmt.Fprintf(os.Stderr, "Usage: %s [flags]\n\n", os.Args[0])
		fmt.Fprintf(os.Stderr, "Flags:\n")
		flag.PrintDefaults()
	}
	flag.Parse()

	// ── Handle -e extract mode ─────────────────────────────────────
	if extractFlag != "" {
		if err := urlExtract(extractFlag); err != nil {
			fmt.Fprintf(os.Stderr, "%sError:%s %v\n", red, rest, err)
			os.Exit(1)
		}
		os.Exit(0)
	}

	// ── Validate required flags ────────────────────────────────────
	if urlFlag == "" {
		fmt.Fprintf(os.Stderr, "%sError:%s URL is required (-u / --url)\n", red, rest)
		flag.Usage()
		os.Exit(1)
	}

	methodFlag = strings.ToUpper(methodFlag)
	if !validMethod(methodFlag) {
		fmt.Fprintf(os.Stderr, "%sError:%s Invalid HTTP method '%s'. Supported: GET, POST, PUT, DELETE, PATCH, HEAD, OPTIONS, CONNECT, TRACE\n",
			red, rest, methodFlag)
		os.Exit(1)
	}

	if threadsFlag < 1 {
		threadsFlag = 1
	}

	// ── Parse wordlists ────────────────────────────────────────────
	wordlists := make(map[string][]string)
	for _, item := range wordlistFlag {
		idx := strings.LastIndex(item, ":")
		if idx == -1 {
			fmt.Fprintf(os.Stderr, "%sError:%s Invalid wordlist format '%s'. Use file.txt:KEY\n", red, rest, item)
			os.Exit(1)
		}
		filePath := strings.TrimSpace(item[:idx])
		key := strings.TrimSpace(item[idx+1:])
		if key == "" {
			fmt.Fprintf(os.Stderr, "%sError:%s Empty key in wordlist '%s'\n", red, rest, item)
			os.Exit(1)
		}
		wl, err := readWordlist(filePath)
		if err != nil {
			fmt.Fprintf(os.Stderr, "%sError:%s Cannot read wordlist '%s': %v\n", red, rest, filePath, err)
			os.Exit(1)
		}
		if len(wl) == 0 {
			fmt.Fprintf(os.Stderr, "%sWarning:%s Wordlist '%s' is empty\n", secondary, rest, filePath)
		}
		wordlists[key] = wl
	}

	// ── Parse headers ──────────────────────────────────────────────
	headers := make(http.Header)
	for _, h := range headerFlag {
		idx := strings.Index(h, ":")
		if idx == -1 {
			fmt.Fprintf(os.Stderr, "%sWarning:%s Invalid header format '%s'. Use Key:Value\n", secondary, rest, h)
			continue
		}
		k := strings.TrimSpace(h[:idx])
		v := strings.TrimSpace(h[idx+1:])
		headers.Set(k, v)
	}
	if cookieFlag != "" {
		headers.Set("Cookie", cookieFlag)
	}

	// ── Build conditions map (for display) ─────────────────────────
	conditions := make(map[string]interface{})
	if len(matchStatusFlag) > 0 {
		conditions["status"] = matchStatusFlag
	}
	if matchSizeFlag > 0 {
		conditions["size"] = matchSizeFlag
	}
	if matchStringFlag != "" {
		conditions["string"] = matchStringFlag
	}
	if invMatchStrFlag != "" {
		conditions["inverted_string"] = invMatchStrFlag
	}

	// ── Print banner ───────────────────────────────────────────────
	printBanner(urlFlag, wordlists, conditions, threadsFlag, methodFlag)

	// ── Generate all payload combinations ──────────────────────────
	combos := cartesianProduct(wordlists)
	totalTasks := len(combos)

	if totalTasks == 0 {
		combos = []map[string]string{{}}
		totalTasks = 1
	}

	fmt.Printf("%s[%s•%s]%s Generated %s%d%s combinations\n\n",
		secondary, rest, secondary, primary, primary, totalTasks, rest)

	// ── Create HTTP client ─────────────────────────────────────────
	cookieJar := newRecordableJar()

	transport := &http.Transport{
		TLSClientConfig: &tls.Config{InsecureSkipVerify: false},
		MaxIdleConns:    threadsFlag,
		IdleConnTimeout: 30 * time.Second,
	}

	client := &http.Client{
		Transport:     transport,
		Timeout:       10 * time.Second,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 10 {
				return http.ErrUseLastResponse
			}
			return nil
		},
		Jar: cookieJar,
	}

	// ── Build tasks ────────────────────────────────────────────────
	tasks := make([]task, 0, totalTasks)
	for i, combo := range combos {
		tURL := urlFlag
		tData := dataFlag

		for k, v := range combo {
			tURL = strings.ReplaceAll(tURL, "FUZZ_"+k, v)
			if tData != "" {
				tData = strings.ReplaceAll(tData, "FUZZ_"+k, v)
			}
		}

		tasks = append(tasks, task{
			id:       i,
			payloads: combo,
			url:      tURL,
			data:     tData,
		})
	}

	// ── Channels ───────────────────────────────────────────────────
	taskCh := make(chan task, totalTasks)
	resultCh := make(chan result, threadsFlag*2)

	var completed int64
	var matchCount int64
	var outMu sync.Mutex

	// ── Start workers ──────────────────────────────────────────────
	var wg sync.WaitGroup
	for w := 0; w < threadsFlag; w++ {
		wg.Add(1)
		go worker(w, client, methodFlag, headers, taskCh, resultCh, &wg)
	}

	// ── Send tasks ─────────────────────────────────────────────────
	go func() {
		for _, t := range tasks {
			taskCh <- t
		}
		close(taskCh)
	}()

	// ── Wait for workers in separate goroutine ────────────────────
	go func() {
		wg.Wait()
		close(resultCh)
	}()

	// ── Process results ───────────────────────────────────────────
	startTime := time.Now()
	for res := range resultCh {
		atomic.AddInt64(&completed, 1)
		done := atomic.LoadInt64(&completed)

		truncated := res.task.url
		if len(truncated) > 70 {
			truncated = truncated[:67] + "..."
		}
		elapsed := time.Since(startTime).Round(time.Second)
		fmt.Printf("\r%s[%s%d/%d%s]%s %s  %s elapsed%s",
			secondary, primary, done, totalTasks, secondary, rest,
			truncated, elapsed, strings.Repeat(" ", 10))

		if res.err != nil {
			if !noErrorsFlag {
				fmt.Printf("\n    %s→ Error%s:%s %v%s\n",
					red, rest, red, res.err, rest)
			}
			continue
		}

		// ── Evaluate conditions ────────────────────────────────────
		statusMatch := true
		if len(matchStatusFlag) > 0 {
			statusMatch = false
			for _, code := range matchStatusFlag {
				if res.statusCode == code {
					statusMatch = true
					break
				}
			}
		}

		sizeMatch := true
		if matchSizeFlag > 0 {
			sizeMatch = res.bodySize >= matchSizeFlag
		}

		stringMatch := true
		if matchStringFlag != "" {
			stringMatch = strings.Contains(res.body, matchStringFlag)
		}

		invStringMatch := true
		if invMatchStrFlag != "" {
			invStringMatch = !strings.Contains(res.body, invMatchStrFlag)
		}

		match := statusMatch && sizeMatch && stringMatch && invStringMatch

		if match {
			atomic.AddInt64(&matchCount, 1)
			fmt.Printf("\n[%s✓%s] %sMatch found!%s\n",
				primary, rest, primary, rest)
			fmt.Printf("    %sArgs%s: %v\n", primary, rest, res.task.payloads)
			fmt.Printf("    %sStatus%s: %d\n", primary, rest, res.statusCode)
			fmt.Printf("    %sSize%s: %d\n", primary, rest, res.bodySize)
			if matchStringFlag != "" {
				fmt.Printf("    %sContains%s: '%s'\n", primary, rest, matchStringFlag)
			}
			if invMatchStrFlag != "" {
				fmt.Printf("    %sDoes NOT contain%s: '%s'\n", primary, rest, invMatchStrFlag)
			}

			outMu.Lock()
			f, err := os.OpenFile(outFlag, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
			if err == nil {
				fmt.Fprintf(f, "date: %s\n", time.Now().Format(time.RFC3339))
				fmt.Fprintf(f, "url: %s\n", res.task.url)
				fmt.Fprintf(f, "args: %v\n", res.task.payloads)
				fmt.Fprintf(f, "status: %d\n", res.statusCode)
				fmt.Fprintf(f, "size: %d\n", res.bodySize)
				fmt.Fprintf(f, "conditions: %v\n", conditions)
				fmt.Fprintf(f, "%s\n", strings.Repeat("─", 30))
				f.Close()
			}
			outMu.Unlock()
		} else if !quiteFlag {
			statusOk := fmt.Sprintf("%s✓%s", primary, rest)
			if !statusMatch {
				statusOk = fmt.Sprintf("%s✗%s", red, rest)
			}
			sizeOk := fmt.Sprintf("%s✓%s", primary, rest)
			if !sizeMatch {
				sizeOk = fmt.Sprintf("%s✗%s", red, rest)
			}
			stringOk := fmt.Sprintf("%s✓%s", primary, rest)
			if !stringMatch {
				stringOk = fmt.Sprintf("%s✗%s", red, rest)
			}
			invStrOk := fmt.Sprintf("%s✓%s", primary, rest)
			if !invStringMatch {
				invStrOk = fmt.Sprintf("%s✗%s", red, rest)
			}

			parts := []string{
				fmt.Sprintf("%s→ Url%s:%s %s", primary, rest, primary, res.task.url),
				fmt.Sprintf("%sStatus%s:%s %d%s(%s)",
					primary, rest, primary, res.statusCode, rest, statusOk),
				fmt.Sprintf("%sSize%s:%s %d%s(%s)",
					primary, rest, primary, res.bodySize, rest, sizeOk),
			}
			if matchStringFlag != "" && invMatchStrFlag != "" {
				parts = append(parts, fmt.Sprintf("%sString%s: %s | %sInverted%s: %s",
					primary, rest, stringOk, primary, rest, invStrOk))
			} else if matchStringFlag != "" {
				parts = append(parts, fmt.Sprintf("%sString%s: %s",
					primary, rest, stringOk))
			} else if invMatchStrFlag != "" {
				parts = append(parts, fmt.Sprintf("%sInverted%s: %s",
					primary, rest, invStrOk))
			}
			fmt.Printf("\n    %s\n", strings.Join(parts, fmt.Sprintf(" %s|%s ", secondary, primary)))
		}
	}

	fmt.Println()

	// ── Save cookie jar ────────────────────────────────────────────
	if cookieJarFlag != "" {
		if err := cookieJar.SaveToFile(cookieJarFlag); err != nil {
			fmt.Fprintf(os.Stderr, "%sWarning:%s Could not save cookie jar: %v\n", secondary, rest, err)
		} else {
			fmt.Printf("%s[%s✓%s]%s Cookies saved to %s\n",
				secondary, primary, secondary, rest, cookieJarFlag)
		}
	}

	// ── Summary ────────────────────────────────────────────────────
	elapsed := time.Since(startTime).Round(time.Millisecond)
	fmt.Printf("\n%s[%s•%s]%s Done! %s%d%s matches found out of %s%d%s requests in %s%v%s\n",
		secondary, rest, secondary, primary,
		primary, atomic.LoadInt64(&matchCount), rest,
		primary, totalTasks, rest,
		primary, elapsed, rest)
	if outFlag != "" && atomic.LoadInt64(&matchCount) > 0 {
		fmt.Printf("%s[%s•%s]%s Results saved to %s%s%s\n",
			secondary, rest, secondary, primary, primary, outFlag, rest)
	}
}

// ─── Worker goroutine ──────────────────────────────────────────────
func worker(
	id int,
	client *http.Client,
	method string,
	headers http.Header,
	taskCh <-chan task,
	resultCh chan<- result,
	wg *sync.WaitGroup,
) {
	defer wg.Done()

	for t := range taskCh {
		var bodyReader io.Reader
		if t.data != "" {
			bodyReader = strings.NewReader(t.data)
		}

		req, err := http.NewRequest(method, t.url, bodyReader)
		if err != nil {
			resultCh <- result{task: t, err: err}
			continue
		}

		for k, vs := range headers {
			for _, v := range vs {
				req.Header.Add(k, v)
			}
		}

		if t.data != "" && req.Header.Get("Content-Type") == "" {
			req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
		}

		start := time.Now()
		resp, err := client.Do(req)
		dur := time.Since(start)

		if err != nil {
			resultCh <- result{task: t, err: err, duration: dur}
			continue
		}

		bodyBytes, readErr := io.ReadAll(resp.Body)
		resp.Body.Close()

		if readErr != nil {
			resultCh <- result{task: t, err: readErr, duration: dur}
			continue
		}

		resultCh <- result{
			task:       t,
			statusCode: resp.StatusCode,
			bodySize:   len(bodyBytes),
			body:       string(bodyBytes),
			duration:   dur,
		}
	}
}
