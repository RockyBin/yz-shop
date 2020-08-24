# python版本的爬虫工具，需要用到 chromedriver 和 selenium，chromedriver 下载地址 https://sites.google.com/a/chromium.org/chromedriver/home
# 如果使用firefox引擎，需要在服务器上安装 firefox 和 geckodriver，geckodriver 下载地址 https://github.com/mozilla/geckodriver/releases
import sys
import time
from selenium import webdriver
from selenium.webdriver.chrome.options import Options

try:
	sdriver = "chrome"
	if len(sys.argv) > 2:
		sdriver = sys.argv[2]
	if sdriver == "chrome":
		# 创建一个参数对象，用来控制chrome以无界面模式打开
		chrome_options = Options()
		chrome_options.add_argument('--headless')
		chrome_options.add_argument('--disable-gpu')
		mobileEmulation = {'deviceName': 'iPhone 8'}
		chrome_options.add_experimental_option('mobileEmulation', mobileEmulation)
		#设置chromedriver不加载图片
		prefs = {"profile.managed_default_content_settings.images": 2}
		chrome_options.add_experimental_option("prefs", prefs)
		# 创建浏览器对象
		path = r'D:\Python\Python38\chromedriver.exe'
		driver = webdriver.Chrome(options=chrome_options,executable_path=path)
	else:
		profile=webdriver.FirefoxOptions()
		profile.set_preference('permissions.default.image',2) #禁止加载图片
		profile.add_argument('-headless') #设置无头模式
		profile.add_argument('--disable-gpu')
		profile.set_preference("general.useragent.override", "Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1")
		path = r'D:\Python\Python38\geckodriver.exe'
		driver = webdriver.Firefox(options=profile,executable_path=path)

	# 访问URL
	url = sys.argv[1]
	driver.get(url)
	#driver.implicitly_wait(3) #隐性等待无法抓取到映客的地址
	time.sleep(2) #先改为有强制等待
	# 调用driver的page_source属性获取页面源码
	pageSource = driver.page_source
	# 打印页面源码
	print(pageSource.encode("utf-8", "ignore"))
	driver.quit()
except:
	traceback.print_exc()
sys.exit()