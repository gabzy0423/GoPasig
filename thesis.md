GoPasig: An Integrated Web-Based Fleet Management, GPS Tracking, and Estimated Arrival Time System for the Pasig City Libreng Sakay Program


IT410 - Capstone Project & Research
Bachelor of Science in Information Technology



A Capstone and Research Study by:

Vargas, Gabriel P.
Francisco, Mandy P.
Alvarez, Renz D.




Submitted to:


2026

TABLE OF CONTENTS

CHAPTER 1

Introduction	3
Background of the Study	4
Statement of the Problem	6
Objectives of the study

CHAPTER 2
	7
Conceptual Literature	13
Theoretical Background	13
Technical Background	17
Development Tools	20
Evaluation System	21
Research Literature	21
Conceptual Model	24
Synthesis	25
Operational Definition of Terms	26



Chapter 1 

THE PROBLEM AND ITS BACKGROUND
 
 
Introduction 
 
Urban bus systems are increasingly evaluated through objective service metrics derived from continuous GPS data rather than solely from schedules or manual reports. Longitudinal studies show that GPS-based tracking enables detailed analysis of travel times, route choice, and recurrent congestion patterns, providing a basis for improving public transport reliability and planning (Costa et al., 2023; Haamer et al., 2025). GPS data analytics have been used to assess headway regularity, punctuality, and service quality across routes, demonstrating that location traces can support both operational decisions and strategic evaluation of bus networks (Zhang et al., 2023; Chawuthai et al., 2023). Complementing these technical insights, passenger surveys indicate that satisfaction is closely linked to predictability, waiting time, and the availability of accurate real-time information on arrivals and service conditions (Public Transport Council, 2023; Ashwini et al., 2023).
Advances in transport technology highlight GPS tracking, high-frequency polling, and lightweight estimation algorithms as practical means to deliver real-time or near-real-time information without requiring complex infrastructure (Yousef & Ragheb, 2026; Ashwini et al., 2023). Within this context, the present study is situated in the area of intelligent transportation systems and location-based services, focusing on the design of a GPS-enabled web-based solution that provides bus location visibility, simple distance–speed–based arrival time estimation, and an administrative interface for route and operations oversight. 

Background of the Study 
In urban public transportation, bus operations are increasingly monitored through continuous GPS data rather than static schedules or manual reporting, with fleet management practices shifting toward integrated digital systems that support real-time service control and operational oversight. GPS-based monitoring enables detailed assessment of travel times, headways, route adherence, and congestion, supporting more precise service control and planning (Costa et al., 2023; Haamer et al., 2025). GPS data analytics can further quantify punctuality and service regularity, providing route-level indicators for evaluating network performance and fleet utilization (Zhang et al., 2023; Chawuthai et al., 2023). Passenger research likewise links satisfaction to predictable waiting times and reliable real-time arrival information, with both institutional assessments and empirical studies confirming that service unpredictability remains a primary driver of reduced public transport usage (Public Transport Council, 2023; Ashwini et al., 2023), underscoring the operational need for accurate, timely, and integrated transport data.  
       As part of Pasig City's Libreng Sakay Program, free bus services operate across multiple fixed routes within the city, providing daily mobility for workers, students, and residents who rely on the program as a primary means of urban transit. The program independently manages its own fleet, drivers, and service schedules under public-sector resource constraints, balancing route coverage, service reliability, and operational efficiency without the data infrastructure available to larger commercial transit operators. Bus drivers function not only as vehicle operators but as frontline service delivery personnel responsible for schedule adherence, passenger management, and real-time coordination with dispatch — making their operational role central to the program's day-to-day service quality. Within this context, the present study focuses on GoPasig: An Integrated Web-Based Fleet Management, GPS Tracking, and Estimated Arrival Time System for the Pasig City Libreng Sakay Program — a GPS-enabled, web-based operations support platform that integrates fleet management, live bus tracking, and estimated arrival time computation for Libreng Sakay routes. 
Many of the program's operational tasks remain partially manual. Trip logging, dispatching, bus status updates, and maintenance records are managed through paper forms, spreadsheets, or informal communication among administrators, drivers, and dispatch personnel. Where GPS or tracking data exist, they are not consistently integrated with schedules, driver assignments, or historical logs, making it difficult to verify route adherence, reconstruct completed trips, or assess route performance systematically. Drivers operate without a structured digital channel for reporting trip status, passenger counts, or service irregularities, further fragmenting the operational data available to administrators and transportation managers.
No existing platform — general-purpose or locally deployed — currently resolves these operational gaps within a single integrated system. Moovit and Google Maps Transit provide commuter-facing route information and estimated arrival times in cities where agencies publish standardized data feeds, but neither offers administrative tools for fleet management, driver coordination, dispatch control, or maintenance tracking, and both depend on data infrastructure the Libreng Sakay Program does not currently maintain (Google, 2024; Moovit, 2024). At the local level, Pasig City previously deployed a transport microsite and utilized sakay.ph to disseminate Libreng Sakay schedules and route updates; however, these were limited to static announcements without live tracking, fleet coordination, or operational data management (Philippine News Agency, 2020). The limitations of these prior efforts — despite being developed for the same program and user base — confirm that the program's operational and informational needs have not yet been met by any deployed solution. 
The consequences of this gap are measurable. Fragmented operational records constrain timely dispatching, disrupt maintenance planning, and limit data-driven route evaluation — directly affecting service reliability for commuters who depend on the Libreng Sakay Program for daily mobility. Research confirms that such fragmented practices limit evaluation of service quality indicators including headway regularity and travel time reliability (Costa et al., 2023; Zhang et al., 2023; Chawuthai et al., 2023), while irregular service and uncertain waiting times reduce passenger satisfaction and perceived service quality (Ashwini et al., 2023; Public Transport Council, 2023). In a publicly funded service, these conditions also constrain transparency and accountability in assessing whether buses, drivers, and routes are being utilized efficiently — weakening the evidentiary basis for policy development and resource allocation by the local government unit. 
To address these conditions, GoPasig proposes a GPS-enabled, web-based platform that consolidates fleet management, live tracking, and arrival time computation within a single system accessible to commuters, administrators, drivers, and transportation managers. The commuter interface provides real-time bus locations and estimated arrival times to reduce waiting uncertainty and support trip planning. The administrative dashboard supports bus status monitoring, driver assignment, dispatch coordination, trip logging, maintenance records, and route performance reporting — replacing fragmented manual practices with structured digital workflows. A driver-facing operational layer enables bus operators to maintain active GPS sessions, report trip status, and coordinate with dispatchers in real time. By integrating GPS tracking, software-based location transmission, and lightweight distance-speed arrival time estimation (Yousef & Ragheb, 2026; Ashwini et al., 2023), GoPasig provides a purpose-built, operationally complete response to the monitoring and coordination gaps of the Libreng Sakay Program. 

Statement of the Problem 
The main problem dealt with in this study is the lack of an integrated web-based fleet management, GPS tracking, and estimated arrival time system for the Pasig City Libreng Sakay Program, which can cause limited operational visibility, inefficient transport coordination, unreliable service information, and difficulty in monitoring bus performance.
Specifically, the study sought to answer the following problems:
1.Commuters do not have access to reliable real-time bus location and estimated arrival time information, resulting in uncertain waiting times and difficulty planning their trips;
2.The reliance on manual trip logs, paper-based records, spreadsheets, and informal communication makes it difficult for administrators to monitor bus status, driver assignments, dispatching activities, schedules, and maintenance records accurately; and,
3.The lack of integrated operational data limits the ability of transportation managers and the local government unit to evaluate route performance, verify route adherence, assess fleet utilization, respond to service disruptions, and make data-driven decisions for improving the Libreng Sakay Program.
 
Objectives of the study 
The general objective of this study is to develop an integrated web-based fleet management, GPS tracking, and estimated arrival time system for the Pasig City Libreng Sakay Program that will improve operational visibility, transport coordination, service information reliability, and bus performance monitoring.
Specifically, it aims to:
1.Design the software with the following features:
a. Real-Time Bus Location Tracking that allows commuters to view the current location of Libreng Sakay buses along their assigned routes
b.Estimated Arrival Time Information that provides commuters with expected bus arrival times at designated stops or waiting areas to reduce uncertainty and support better trip planning;
c. Fleet and Bus Status Management that enables administrators to monitor bus availability, active and inactive units, delayed buses, and buses under maintenance;
d.Mobile-Responsive Commuter Interface that allows commuters to access bus location, estimated arrival time, route information, and service updates conveniently through mobile devices; 
e. Driver Assignment and Dispatch Management that allows administrators to manage driver schedules, route assignments, dispatching activities, completed trips, and deployment updates;

f.Schedule and Route Monitoring that helps transportation managers verify route adherence, monitor trip schedules, and identify route deviations or service delays;
g.Maintenance Record Management that allows administrators to store, update, and monitor bus maintenance history, repair schedules, inspection records, and service status;
h.Operational Reports and Analytics that generate reports on bus performance, fleet utilization, route activity, service disruptions, and other relevant data needed by transportation managers and the local government unit; and,
i.Service Update and Notification Feature that provides timely information regarding delays, route changes, bus availability, and other service-related announcements for commuters and system users.
2.Create the software using appropriate development tools, database management systems, web technologies, and frameworks suitable for building a secure, responsive, and integrated web-based fleet management, GPS tracking, and estimated arrival time system.
3.Test and improve the software based on ISO/IEC 25010 using the following criteria:
a. Functional Suitability, Reliability, Performance Efficiency, and Usability for commuters, administrators, transportation managers, and local government unit users; and,
b.Security, Compatibility, Maintainability, and Portability for the proponents and system administrators.
4.Evaluate the performance of the developed software using the ISO/IEC 25010 evaluation instrument to determine its acceptability, effectiveness, and suitability in addressing the identified problems of the Pasig City Libreng Sakay Program.
 
Scope and Limitations of the Study
This study dealt with the design, creation, testing and improvement, and evaluation of GoPasig: An Integrated Web-Based Fleet Management, GPS Tracking, and Estimated Arrival Time System for the Pasig City Libreng Sakay Program. It aims to provide a centralized platform for monitoring Libreng Sakay bus operations, improving commuter access to real-time service information, and supporting administrators and transportation managers in coordinating fleet activities.
The system is intended for commuters, administrators, transportation managers, and the Pasig City local government unit. Commuters can access bus locations, estimated arrival times, route information, and service updates through a mobile-responsive interface. Administrators can manage bus status, driver assignments, dispatching activities, schedules, trip records, and maintenance records. Transportation managers and the local government unit can use operational reports and analytics to assess route performance, verify route adherence, monitor fleet utilization, and support data-driven decisions.
The system covers major features such as real-time bus location tracking, estimated arrival time computation, mobile-responsive commuter access, fleet and bus status management, driver assignment and dispatch management, schedule and route monitoring, maintenance record management, service update notifications, and operational reports and analytics. These features are designed to replace fragmented manual records and informal communication with organized digital records, map-based monitoring, and structured operational data.
The study will be conducted within the development and evaluation period set by the researchers. The completed system will be tested and evaluated using the ISO/IEC 25010 software quality model, focusing on functional suitability, reliability, performance efficiency, usability, security, compatibility, maintainability, and portability. The evaluation will determine the acceptability and effectiveness of the system based on the assessment of intended users and technical evaluators.
However, the system focused only on the Pasig City Libreng Sakay Program and its bus-related operations. It does not cover other transportation services such as jeepneys, tricycles, taxis, ride-hailing services, private bus companies, or transport programs from other cities. The system is designed according to the operational needs of the Libreng Sakay Program and may require modification before it can be applied to other transport services or local government units.
The study does not include online payment, ticket booking, fare collection, automatic traffic signal control, advanced artificial intelligence-based traffic prediction, or full integration with external government transportation databases. Since the Libreng Sakay Program is a free-ride service, ticketing and payment-related functions are outside the coverage of the system. The study is also limited to software development and evaluation; actual city-wide deployment, long-term maintenance, user training, procurement of GPS devices, and institutional adoption will depend on the approval, resources, and technical capacity of the concerned offices.

Significance of the Study
Generally, the result of this study could provide great benefit to the following: 
The Commuters. This study can benefit commuters by providing access to real-time bus location, estimated arrival time, route information, and service updates through a mobile-responsive interface. This can help reduce uncertainty in waiting times, improve trip planning, and make the use of the Pasig City Libreng Sakay Program more convenient and reliable.
The Administrators. This study can help administrators manage daily bus operations more efficiently by providing digital tools for monitoring bus status, driver assignments, dispatching activities, trip schedules, and maintenance records. It can reduce reliance on paper-based logs, spreadsheets, and informal communication, resulting in more organized and accurate operational records.
The Transportation Managers. This study can assist transportation managers in monitoring route performance, verifying route adherence, identifying service delays, and assessing fleet utilization. Through operational reports and analytics, the system can support better planning, faster response to service disruptions, and more informed decisions for improving the Libreng Sakay Program.
The Pasig City Local Government Unit. This study can provide the local government unit with a useful platform for improving transparency, accountability, and efficiency in managing a publicly funded transportation service. The system can help generate relevant data that may support policy development, resource allocation, service evaluation, and future improvements in public transportation programs.
The Drivers and Dispatch Personnel. This study can support drivers and dispatch personnel by providing a more structured process for assigning routes, monitoring trips, and updating bus status. It can help improve coordination between field operations and administrative monitoring, reducing confusion in dispatching and daily trip management.
The Future Researchers. This study can serve as a reference for future researchers who intend to develop related systems in fleet management, intelligent transportation systems, GPS tracking, estimated arrival time computation, and public transport monitoring. It may also provide a basis for future enhancements, such as improved arrival time prediction, broader route coverage, mobile application development, or integration with other government transportation platforms.



Chapter 2
 
REVIEW OF RELATED LITERATURE, STUDIES AND SYSTEMS 
 
This chapter presents the existing literature and other publicly available information that supports the need for this research study. Conceptual and research literatures are presented which serve as references and sources of insights for the researcher. It also includes conceptual models or framework in order to establish the probable solution to the research problem. In addition, a synthesis is also presented to summarize, give analysis and conclude on the literature and studies presented. 
 	 
Conceptual Literature 
Theoretical Background 
 
The development of a Fleet Management System such as GoPasig is supported by theories and models related to user adoption, travel time reliability, real-time information in public transport, and GPS-based positioning and filtering. These perspectives justify the design of GoPasig as an operational tool for the Pasig City Libreng Sakay Program and explain the choice of algorithms used for tracking and estimated arrival time (ETA) generation.
Intelligent transportation systems (ITS) integrate sensing, communication, and control to support real-time management of transport services. In bus operations, ITS typically couple on-board GPS devices with operations dashboards, structured logs, and rule-based workflows to improve monitoring, dispatching, and service coordination (Costa et al., 2023; Haamer et al., 2025). GoPasig adopts this ITS orientation by combining continuous vehicle location tracking with browser-based administrative interfaces for trip logging, dispatch decisions, vehicle-status control, and scheduling. The underlying assumption, consistent with prior work on GPS-based monitoring (Zhang et al., 2023; Chawuthai et al., 2023), is that systematically captured operational data enable more consistent headways, better resource allocation, and clearer visibility of route performance.
The concept of Travel Time Reliability is also relevant to the study. It underlines the value of consistent travel and arrival times for commuter satisfaction and daily trip planning. Research indicates that passengers often experience inconvenience when bus schedules are uncertain, causing longer waiting periods and the need to allocate extra travel time (Ashwini et al., 2023; Public Transport Council, 2023). By generating estimated arrival time (ETA) information, GoPasig aims to reduce uncertainty and help commuters manage their schedules more effectively.
Raw GPS signals are subject to noise, short-term jumps, and temporary loss of accuracy due to environmental and hardware factors. Kalman filtering is a recursive estimation technique that combines prior state estimates with new measurements to produce smoothed, statistically grounded estimates of a system’s true state. Applied to vehicle tracking, a Kalman filter treats each bus’s position and velocity as a dynamic state, refining raw GPS readings into a more stable trajectory. In GoPasig, Kalman-based filtering is used conceptually to improve positional accuracy and continuity before these positions are used for distance and ETA computation. This is particularly important in urban corridors where GPS jitter could otherwise distort calculated speeds and remaining distances, and thus undermine the reliability of ETAs emphasized in the literature on real-time transport information (Yousef & Ragheb, 2026; Ashwini et al., 2023).  
For systems that operate on the Earth’s surface, geographic distance between two points is commonly modeled using great-circle formulas. The Haversine formula computes the shortest distance over the earth’s sphere between two latitude–longitude pairs, making it a standard choice for GPS-based applications that do not require full road-network modeling. In GoPasig, filtered bus positions are combined with Haversine-based distance calculations to estimate the remaining distance from the bus to upcoming stops or control points along the Libreng Sakay routes. 

ETA estimation is then formulated as a velocity-based prediction problem. Consistent with studies highlighting simple, infrastructure-feasible models for real-time applications (Yousef & Ragheb, 2026), GoPasig uses a distance–speed approach in which the predicted arrival time is approximated as:
Formula:
​ETA =remaining route distance​ current or smoothed speed

The current or smoothed speed is derived from consecutive filtered GPS positions over time. This approach avoids complex, data-intensive models while still leveraging continuous tracking to generate responsive arrival-time estimates, aligning with prior work demonstrating the practicality of GPS-derived, speed-based arrival prediction in bus systems (Costa et al., 2023; Zhang et al., 2023). 
Operational research on bus systems emphasizes that effective control depends not only on vehicle tracking but also on the structured representation of trips, resources, and operational events. Central constructs include clearly defined fleet states, systematically maintained trip records, and explicit dispatch rules governing vehicle and driver assignments (Chawuthai et al., 2023). GoPasig incorporates these constructs through structured electronic trip logs, timetable- and shift-based scheduling views, maintenance records with reminders, and rule-based dispatch interfaces. Together, these elements translate theoretical principles on integrated, log-oriented information systems into practice, enabling more systematic control of headways, fleet utilization, and maintenance within the Libreng Sakay Program.
 
Technical Background
The system consists of: (1) a commuter-facing web interface for viewing live bus locations, routes, and estimated arrival times, (2) an administrative dashboard for monitoring vehicle movement and operational metrics, and (3) a driver-facing operational layer through which bus operators follow assigned routes and schedules, maintain an active GPS session during trips, monitor passenger capacity, and coordinate with dispatchers when service conditions require. The back end is implemented using Laravel version 11 with a MySQL relational database hosted on a local server, enabling persistent trip logging and concurrent multi-user access. The front end uses Blade Templates, Tailwind CSS, Livewire, and JavaScript integrated with the Google Maps JavaScript API to render routes and bus positions on an interactive map, while a software-based GPS tracking application on driver-assigned mobile devices continuously transmits coordinates to the server for processing.
Prior public transport platforms — limited to printed schedules, static timetables, or closed operator displays — provided no real-time visibility to commuters and left administrators without actionable operational data. Contemporary Intelligent Transportation Systems-aligned systems address this by coupling software-based GPS tracking with live dashboards and structured databases to generate dynamic arrival estimates and route-level performance indicators (Barbeau et al., 2023; Zhang et al., 2023; Costa et al., 2023). GoPasig adopts this architecture for the Libreng Sakay Program, applying Kalman-filtered GPS coordinates to distance–speed estimation via the Haversine formula — an approach empirically validated for computational efficiency and predictive accuracy in urban fixed-route bus operations (Patel, Shah, & Mehta, 2023; Rahman, Islam, & Hasan, 2023; Yousef & Ragheb, 2026).
Four technical constraints specific to the deployment environment are anticipated. First, GPS signal attenuation in Pasig City's built-up corridors is addressed through Kalman smoothing applied to raw coordinate streams prior to speed and distance calculations. Second, gaps caused by intermittent mobile data coverage are handled by retaining the last valid position server-side and surfacing a staleness indicator on the commuter interface when updates exceed the defined timeout threshold. Third, Google Maps JavaScript API quota constraints are managed by consolidating coordinate updates into batched map render calls per polling cycle rather than per-coordinate requests. Fourth, server response latency under concurrent user and vehicle load is controlled through indexed MySQL queries on high-frequency tables and Laravel route caching, maintaining real-time interface responsiveness within the program's current infrastructure capacity.

Intelligent Transportation Systems in Urban Public Bus Operations
		Intelligent Transportation Systems integrate information and communication technologies into transport operations to improve service efficiency, schedule adherence, and passenger information reliability. In fixed-route bus networks, ITS frameworks couple software-based GPS tracking with centralized dashboards, structured operational databases, and real-time analytics to support vehicle supervision, dispatch coordination, and service monitoring (Barbeau et al., 2023; Zhang et al., 2023). Empirical evidence confirms that GPS-based ITS platforms improve route control and incident response by making vehicle positions and operational events continuously visible to administrators, while reliable arrival information reduces commuter uncertainty and strengthens perceived service quality (Costa et al., 2023; Hassan et al., 2025; Abduljabbar et al., 2024).
		GoPasig applies these principles to the Pasig City Libreng Sakay Program, where no integrated ITS infrastructure currently exists. By combining live GPS tracking, a structured operational database, and role-differentiated administrative workflows within a single platform, the system addresses the program's monitoring and coordination gaps as an operationally complete fleet management solution rather than a standalone tracking tool.
		Real-Time GPS Fleet Tracking
		In fixed-route urban bus operations, GPS transmission accuracy and polling frequency directly determine the reliability of both commuter-facing position displays and administrator-facing operational data (Yousef & Ragheb, 2026; Patel, Shah, & Mehta, 2023). Frequent coordinate updates produce more accurate positional traces and more responsive fleet status visibility, but impose proportionally higher server and API load — necessitating a polling interval calibrated to the operational scale of the deployment.
		For GoPasig's deployment scale along Libreng Sakay’s fixed urban routes, a five- to ten-second polling interval was adopted — sufficient to produce responsive positional traces without exceeding the server and API load constraints of the program's current infrastructure. Coordinates are transmitted via HTTP POST from a software-based GPS tracking application installed on driver-assigned mobile devices. Because GPS signals in Pasig City's built-up corridors are subject to positional noise and short-term jitter, incoming coordinates are processed through Kalman-based smoothing before use in any downstream computation or map rendering. Maintaining an active GPS session within the application during trips is the operational responsibility of the assigned bus driver, whose compliance directly determines the completeness and reliability of position data available to the fleet monitoring dashboard and ETA computation pipeline. This filtered position stream serves as the authoritative data source for both the commuter-facing map interface and the administrative dashboard, ensuring displayed bus positions reflect stable location estimates rather than raw signal fluctuations.
		Estimated Arrival Time Computation
		For a publicly managed free-ride bus service operating without access to commercial traffic data feeds or dedicated prediction infrastructure, ETA computation must rely on methods that derive reliable arrival estimates solely from the system's own GPS data stream.  Distance–speed models applied to continuously updated GPS positions provide sufficient predictive accuracy for fixed-route urban bus operations without requiring external traffic data feeds or computationally intensive machine learning pipelines (Rahman, Islam, & Hasan, 2023; Costa et al., 2023; Yousef & Ragheb, 2026).
		GoPasig derives estimated arrival times using the Haversine formula to calculate the great-circle distance between a bus's current Kalman-filtered position and each upcoming stop along its assigned route. This remaining distance is divided by the smoothed speed computed from consecutive GPS positions over time, producing a continuously updated arrival estimate at each polling cycle. The method is computationally lightweight, operates entirely on data generated within the system, and remains viable under the Libreng Sakay Program's current technical and resource constraints.

Development Tools
Visual Studio Code
	Visual Studio Code serves as the primary Integrated Development Environment for GoPasig. It was selected for its lightweight performance, extensive extension support, and zero licensing cost. Extensions utilized include Laravel Blade Snippets, Tailwind CSS IntelliSense, and the REST Client for API endpoint testing. Its integrated terminal supports Artisan command execution and NPM build processes within a unified workspace.
Laravel Framework (v11)
		Laravel version 11 serves as the back-end framework of GoPasig, providing the Model-View-Controller structure, routing, RESTful API management, and Eloquent ORM necessary to process GPS data, execute ETA computations, and manage database-to-interface communication. Built-in authentication scaffolding, form request validation, and role-based access control secure administrative modules and restrict sensitive operational data to authorized users. The framework version is documented to support reproducibility and maintainability evaluation under ISO/IEC 25010. GoPasig utilizes Blade, Laravel's native templating engine, to structure all system views through template inheritance and reusable components, ensuring consistent layout across the commuter interface and administrative dashboard. Blade components are used in conjunction with Livewire to define interface regions selectively re-rendered on each GPS polling cycle. 
Livewire (v3)
Livewire is a full-stack Laravel component framework that enables real-time interface reactivity without a separate JavaScript framework. In GoPasig, it manages the polling mechanism that refreshes bus position markers, fleet status indicators, and ETA values across both interfaces at each coordinate update interval. Its server-driven rendering model handles state management and DOM diffing server-side, ensuring cross-browser consistency while reducing front-end complexity.
Tailwind CSS (v3)
a utility-first framework used to style all GoPasig interfaces. Its responsive utility classes enable layout adaptation across mobile and desktop screen sizes without custom media query logic, directly supporting the system's mobile-responsive commuter interface requirement. Content purging is applied during production builds to minimize stylesheet payload and improve load performance on variable mobile networks. 
MySQL
A relational database management system for all persistent operational data in GoPasig, including GPS coordinate logs, trip records, driver and vehicle assignments, dispatch events, maintenance histories, and route performance summaries. It was selected for its native compatibility with Laravel's Eloquent ORM and its capacity for concurrent multi-user transactions at the operational scale of the Libreng Sakay Program. Query indexing is applied to high-frequency tables to sustain responsive data retrieval as operational records accumulate. 
Google Maps JavaScript API
Google Maps JavaScript API renders interactive maps, route polylines, bus position markers, and stop overlays on both GoPasig interfaces. It was selected over Leaflet.js with OpenStreetMap for its superior mapping accuracy within Metro Manila and its well-documented JavaScript SDK. API consumption is managed through batched coordinate update calls per polling cycle to operate within quota limits. A fallback static route view is provided on the commuter interface during temporary API unavailability to maintain basic route visibility. 
Evaluation System (ISO/IEC 25010)
	Systems and Software Quality Requirements and Evaluation standard that defines a hierarchical model of software product quality characteristics used to assess the acceptability and suitability of a developed system. It serves as the primary evaluation instrument for GoPasig, with assessment conducted across eight quality characteristics directly mapped to the system's operational requirements and user groups. Functional Suitability determines whether GoPasig correctly and completely performs its intended fleet management, tracking, and ETA functions. Reliability assesses the system's capacity to maintain stable operation under the concurrent demands of active bus monitoring and multi-user access. Performance Efficiency evaluates server response times and resource utilization under real-time GPS polling and simultaneous user load. Usability measures the degree to which commuters, administrators, and transportation managers can operate their respective interfaces effectively and without unnecessary complexity. Security examines the adequacy of authentication, access control, and data protection mechanisms safeguarding administrative functions and operational records. Compatibility verifies consistent system behavior across the browsers and devices used by the program's intended user groups. Maintainability evaluates the structural quality of the codebase with respect to modification, correction, and future enhancement. Portability determines the extent to which the system can be redeployed across different server environments without requiring significant rework. 
Research Literature 
Costa et al. (2023) demonstrated that real-time GPS data enables accurate analysis of commuter movement patterns, establishing GPS-based analytics as a reliable basis for travel estimation and route monitoring in public transport systems. Building on this, Haamer et al. (2025) applied GPS-based mobility data to assess public transport accessibility, finding that location analytics can systematically identify route inefficiencies and service gaps that static reporting methods fail to detect. Together, these studies establish continuous GPS data collection as the operational foundation for evidence-based fleet monitoring — a principle directly reflected in GoPasig's live tracking architecture for the Libreng Sakay Program.   
Chawuthai et al. (2023) assessed bus service quality through GPS data analytics and found that tracking technologies provide measurable indicators of route performance, reliability, and operational consistency, enabling transport operators to identify service deficiencies and strengthen decision-making through real-time operational data. Zhang et al. (2023) extended this finding to schedule management, demonstrating that continuous vehicle tracking significantly improves schedule adherence and accelerates operational response to service disruptions. GoPasig operationalizes both findings through an administrative dashboard that provides route-level performance visibility and dispatch coordination tools aligned with the monitoring needs of the Libreng Sakay Program.  
Abduljabbar et al. (2024) investigated ETA systems in smart transportation and found that accurate arrival predictions reduce commuter uncertainty and support more informed trip planning, identifying ETA functionality as among the most valued features in digital transit platforms. Complementing this, Patel, Shah, and Mehta (2023) reported high predictive accuracy for distance–speed estimation models in urban bus contexts, while Rahman, Islam, and Hasan (2023) found that integrating Haversine-based distance calculation with linear speed estimation improves monitoring efficiency and reduces passenger uncertainty. Yousef and Ragheb (2026) further established that higher GPS update frequency directly increases positional accuracy and ETA reliability. Collectively, these studies justify GoPasig's adoption of a Kalman-filtered, Haversine–velocity ETA computation framework as both technically sound and practically feasible for fixed-route urban bus operations. 
Moovit is a widely deployed transit information platform that aggregates route schedules, real-time arrival data, and service alerts across multiple cities, including Metro Manila, through a commuter-facing mobile interface (Moovit, 2024). While the platform provides arrival estimates and route guidance where agency data feeds are formally integrated, it does not include administrative modules for fleet status management, driver assignment, dispatch coordination, or maintenance tracking. Its architecture assumes that transit agencies independently maintain operational back-end systems — infrastructure the Libreng Sakay Program does not currently possess — limiting Moovit's applicability as a standalone solution for the program's operational needs. 
Google Maps Transit provides route guidance, schedule information, and, in cities with formal General Transit Feed Specification integration, real-time vehicle positions through its standard navigation interface (Google, 2024). However, the platform functions exclusively as a passenger information tool and offers no operational management capability for fleet administrators. GTFS data publication — a prerequisite for Google Maps Transit integration — requires agencies to maintain and continuously update standardized structured data feeds, a technical and administrative requirement that exceeds the Libreng Sakay Program's current data management capacity. 
The most directly relevant prior deployment is Pasig City's transport microsite, launched during the COVID-19 pandemic period, which provided Libreng Sakay route schedules and service updates to residents, supplemented by routing information through sakay.ph, a third-party mobile routing application (Philippine News Agency, 2020). Despite addressing the same program and user base as GoPasig, this deployment was limited to static schedule announcements and general route information, without live GPS tracking, fleet coordination tools, driver management, or operational data archiving. The absence of these capabilities in a prior city-initiated digital effort for the Libreng Sakay Program directly confirms the operational gap that GoPasig is designed to close. 
The reviewed studies collectively establish that GPS-based tracking, distance–speed ETA computation, and integrated operational dashboards are effective and validated components of public transport management. However, no reviewed study or deployed system addresses the combined fleet management, live tracking, and ETA requirements of a free-ride, LGU-managed fixed-route bus service operating without standardized data infrastructure in a Philippine urban context. GoPasig is positioned to fill this gap by integrating the technical approaches validated in the literature within a purpose-built platform aligned with the specific operational structure of the Pasig City Libreng Sakay Program.



Conceptual Model  
On the basis of the concepts, theories, and findings of related literature and studies, a conceptual model is developed that served as a guide in conducting this study. 
 

Figure 1. The Conceptual Model of the Study 
 
Figure 1 illustrates ____________________________________________  
________________________________________________________________
________________________________________________________________
________________________________________________________________ ________________________________________________________________
______________________. 
 
Synthesis
The reviewed literature establishes GPS-based tracking as the operational foundation of effective public transport monitoring. Costa et al. (2023) and Haamer et al. (2025) confirm that continuous GPS data enables reliable analysis of commuter movement, route performance, and service gaps — findings consistent with Zhang et al. (2023) and Chawuthai et al. (2023), who demonstrate that real-time tracking improves schedule adherence and operational decision-making. While these studies collectively validate GPS monitoring as a basis for evidence-based transport management, none addresses its integration with fleet management workflows, driver coordination, or maintenance record keeping within a unified platform — limiting their direct applicability to an operationally complex, LGU-managed program such as the Libreng Sakay Program.
Abduljabbar et al. (2024) establish ETA functionality as among the most consequential features of digital transit platforms for commuter satisfaction, while Patel, Shah, and Mehta (2023), Rahman, Islam, and Hasan (2023), and Yousef and Ragheb (2026) provide converging technical validation for Haversine-based distance–speed models using Kalman-filtered GPS positions as a computationally feasible method for fixed-route urban bus ETA computation. These studies are methodologically complementary and consistent in their findings; however, all were conducted in international contexts with established transit data infrastructure. None evaluates the feasibility or accuracy of these methods within a Philippine LGU-managed free-ride service operating without standardized data feeds — a gap the present study partially addresses through system implementation and ISO/IEC 25010-based evaluation.
The reviewed systems reinforce this gap from a deployment standpoint. Moovit and Google Maps Transit demonstrate that passenger-facing transit platforms are technically mature but dependent on agency-published data feeds unavailable to the Libreng Sakay Program. The Pasig City transport microsite and sakay.ph represent the closest prior digital deployment for the same program, yet their limitation to static announcements confirms that live tracking, fleet coordination, and integrated operational management have never been achieved for Libreng Sakay. Across both literature and systems, the consistent finding is that the individual technical components of an effective public bus management system are validated, but their integration within a purpose-built platform for a free-ride LGU bus service in a Philippine urban context remains unaddressed. GoPasig directly responds to this gap by consolidating GPS fleet tracking, Haversine–velocity ETA computation, and administrative management workflows within a single system designed for the operational structure and constraints of the Pasig City Libreng Sakay Program.

 
Operational Definition of Terms 
 	Administrative Dashboard refers to the web-based interface within GoPasig through which administrators and transportation managers monitor fleet status, manage driver assignments, oversee dispatch activities, review trip logs, and access operational reports for the Libreng Sakay Program.
Dispatch Management refers to the process within GoPasig through which administrators assign buses and drivers to specific routes, coordinate departure schedules, and issue deployment updates in response to real-time service conditions.
Estimated Arrival Time (ETA) refers to the projected time at which a Libreng Sakay bus is expected to reach a designated stop, computed in GoPasig using the Haversine–velocity model applied to Kalman-filtered GPS coordinate streams.
Fleet Management refers to the administrative functions within GoPasig for monitoring and maintaining the Libreng Sakay bus fleet, encompassing vehicle status classification, maintenance record keeping, inspection scheduling, and utilization tracking.
GPS Tracking refers to the software-based process in GoPasig through which real-time geographic coordinates of Libreng Sakay buses are continuously transmitted from driver-assigned mobile devices to the server for position processing, map rendering, and ETA computation.
Haversine Formula refers to the mathematical function used in GoPasig to compute the great-circle distance between a bus's current filtered GPS position and each upcoming stop along its assigned route, serving as the distance input for arrival time estimation.
Kalman Filtering refers to the recursive smoothing process applied in GoPasig to raw GPS coordinate streams to suppress positional noise and signal jitter introduced by urban signal attenuation, producing stable location estimates for downstream computation and map rendering.
Libreng Sakay Program refers to the Pasig City local government unit's free public bus service operating on fixed urban routes, which serves as the organizational context and primary beneficiary of the GoPasig system.
Mobile-Responsive Interface refers to the commuter-facing web interface of GoPasig, designed to adapt across mobile and desktop screen sizes, providing commuters with access to live bus locations, estimated arrival times, route information, and service updates through their personal devices.
Operational Reports and Analytics refers to the summary data views generated by GoPasig for transportation managers and the local government unit, covering fleet utilization, route activity, bus performance, and service disruptions to support evidence-based service evaluation and policy development.
Route Monitoring refers to the real-time and historical tracking functions within GoPasig used by transportation managers to verify route adherence, identify path deviations, and detect service delays along Libreng Sakay routes.
Service Update Notification refers to the feature within GoPasig that disseminates timely announcements on service delays, route changes, and bus availability to commuters and system users through the platform interface.
Software-Based GPS Tracking Application refers to the mobile application installed on driver-assigned devices in GoPasig that captures and transmits real-time GPS coordinates to the server during active trips, replacing dedicated hardware tracking units with a software-implemented location reporting mechanism.
Trip Logging refers to the structured digital recording function within GoPasig through which trip data — including departure times, route completion, driver assignments, and passenger counts — are stored in the system database, replacing the manual paper-based records previously used by the LibreCng Sakay Program.





                                                      Chapter 3
DESIGN AND METHODOLOGY 
This chapter presents the project design, project development, systems development model, methods and instruments used in testing and evaluating the software, population and sample, as well as the statistical treatment of data. 
Project Design 
This study employed both the descriptive and developmental methods of research in the design, creation, testing, and evaluation of GoPasig: An Integrated Web-Based Fleet Management, GPS Tracking, and Estimated Arrival Time System for the Pasig City Libreng Sakay Program. The descriptive method established the factual and operational basis for the system's requirements, while the developmental method directed the systematic construction and evaluation of the software solution.
The descriptive method was used to examine and document the existing operational conditions of the Pasig City Libreng Sakay Program, particularly its dependence on manual trip logs, paper-based records, spreadsheets, and informal communication among administrators, drivers, and dispatch personnel. Existing platforms — including Moovit, Google Maps Transit, the Pasig City transport microsite, and sakay.ph — were likewise examined to confirm that no deployed solution addresses the program's combined fleet management, real-time tracking, and estimated arrival time requirements. The findings from this descriptive examination defined the functional scope and user requirements upon which GoPasig was designed.
The researchers applied the developmental method in order to design, construct, test, and evaluate GoPasig as a purpose-built operational software system for the Libreng Sakay Program. Development followed the iterative phases of the Agile model — requirements gathering, system design, development, testing, review and feedback, and deployment and evaluation — with each sprint producing a functional system increment reviewed against the quality characteristics of the ISO/IEC 25010 software quality standard. The completed system was then formally evaluated by its intended users to determine its acceptability, effectiveness, and suitability in addressing the identified operational gaps of the program.


                         Figure 2. The Block Diagram of Fleet Management System
Figure 2 illustrates the procedural flow and structural organization of the Fleet Management System, detailing how disparate user roles interact with the centralized technical infrastructure. The process begins with the Commuter Interface and Driver (Onboard) modules, which act as the primary data entry points for real-time interaction and GPS telemetry. This data is fed into the System Processing block, the core engine responsible for business logic execution and ETA computations. Once processed, information is synchronized across specialized functional blocks—including Fleet Management, Dispatch & Operations, and Route Management—ensuring that administrative oversight is based on live operational data. All system activities are logged within the Centralized Database, which serves as the single source of truth for the Reports & Analytics module to generate performance insights. This modular architecture ensures that service notifications are dispatched to commuters accurately while providing administrators with the tools necessary to monitor bus status, maintenance, and route adherence effectively. 
 
 
 


Figure 3. The Use Case Diagram of Fleet Management System
Figure 3 illustrates the use case diagram procedure for the Fleet Management System, defining the functional boundaries of the application and the specific interactions between external actors and system processes. The system categorizes tasks into four distinct roles: the Commuter, who interacts with front-end features like viewing live bus locations and estimated times of arrival (ETA); the Driver, responsible for providing the data stream through GPS updates and trip status reports; the Fleet Operator, who focuses on high-level utilization and route adherence; and the Administrative actor, who maintains full oversight of fleet logistics, driver assignments, and maintenance scheduling. Central to this procedure is the Send GPS Location use case, which acts as a core dependency for real-time tracking features, while the Generate Report and Analytics function aggregates data from various operational inputs to support data-driven decision-making. By mapping these relationships, the diagram ensures that every requirement—from user authentication to automated service notifications—is logically assigned to its respective stakeholder within the urban transit ecosystem. 

  Figure 4. Admin Live fleet Map
Figure 4 illustrates the admin live fleet map procedure, detailing a systematic operational flow designed for real-time transit oversight. The process begins with the administrator successfully logging into the dashboard and initializing the map interface, which triggers a connection to the Google Maps API and establishes a live data stream. The system then fetches current vehicle coordinates from the database, categorized by their active status, and renders these units as interactive markers on the map. To maintain precision, a synchronization loop is implemented to update bus positions every few seconds, allowing the admin to monitor movement, apply route-specific filters, or select individual units to view detailed telemetry such as plate numbers and estimated arrival times. This continuous feedback loop ensures that the management team has an accurate, high-fidelity view of the entire fleet’s performance at any given moment 





  Figure 5. Admin Bus Management 
Figure 2 illustrates the admin bus management procedure, establishing a structured CRUD (Create, Read, Update, Delete) framework to maintain the fleet's data integrity. The workflow initiates with the system fetching the comprehensive fleet list from the database and displaying it in a centralized management table, allowing the administrator to oversee the status of all registered units. Depending on the operational requirement, the admin can then trigger specific sub-processes: adding a new vehicle with validated plate information, updating existing bus details, or adjusting operational statuses such as "Active" or "Under Maintenance." Every action is governed by a validation gate to prevent duplicate entries or data conflicts, ensuring that any changes are accurately synchronized with the database before the interface refreshes to reflect the updated fleet configuration. 

                                    Figure 6: Admin Driver Management 
Figure 6 illustrates the admin driver management procedure, focusing on the systematic oversight of personnel and their accountability within the transit system. The process begins with the system retrieving a comprehensive directory from the database, displaying a list of all registered drivers alongside their current assignment status and license validity. Through this interface, the administrator can register new drivers by inputting validated credentials, update existing profiles to reflect changes in contact information or license renewals, and manage the critical link between drivers and specific vehicles. To ensure operational safety, the flow includes a validation stage that checks for scheduling conflicts or expired documentation, only committing changes to the database once all requirements are met. This structured approach guarantees that the "Driver Directory" remains a reliable source of truth for the Dispatch and Analytics modules. 
                                  

            Figure 7: Admin Dispatch Management 
Figure 7 illustrates the admin dispatch management procedure, acting as the operational hub that bridges static fleet assets with active transit services. The workflow commences with the system fetching real-time data to identify the "Available Pool" of buses and drivers who are currently on-duty and unassigned. Once resources are verified, the administrator selects a specific route and pairs a vacant bus with an available driver to create a new trip assignment. A critical validation gate then checks for scheduling overlaps or maintenance conflicts to ensure the assignment is viable. Upon confirmation, the trip is saved to the database, which instantly triggers an update to the Live Map and notifies the assigned driver. This coordination ensures that the transit network operates at peak capacity with clear accountability for every active unit. 



      Figure 8: Admin Route Monitoring 
Figure 8 illustrates the admin route monitoring procedure, providing a high-level strategic view of the entire transit network's health. The process begins with the system fetching the predefined route architectures and overlaying them with active trip data to create a live performance snapshot. The administrator can then select specific routes to analyze key metrics such as headway—the time interval between buses—and average transit speeds to detect localized congestion. A significant feature of this flow is the Geofence and Path Validation check; the system automatically monitors if active units are deviating from their assigned polylines. If the route performance falls below optimal thresholds (e.g., excessive bunching or long gaps), the admin can trigger a response directly from the monitoring interface, such as notifying drivers or adjusting dispatch frequency. This continuous oversight ensures that the transit service remains reliable and that individual route bottlenecks are addressed before they impact the wider network.


     Figure 9: Admin schedule management 
Figure 9 illustrates the admin schedule management procedure, which serves as the forward-planning engine for the GoPasig service. The process begins with the system fetching a "merged inventory" of both fleet and driver availability, ensuring that only active, non-maintenance vehicles and on-duty personnel are eligible for scheduling. The administrator then interacts with the dashboard to either create a new shift or modify an existing one, inputting specific parameters such as route assignments, start/end times, and assigned resources. A fundamental component of this flow is the Conflict Resolution Engine; before any schedule is finalized, the system performs a logic check to prevent double-booking a single bus or driver across overlapping time slots. Once the combination is validated as "Conflict-Free," the admin saves the entry, which effectively "locks" those resources for the specified duration. The system then automatically publishes the schedule, pushing notifications to the relevant driver terminals and updating the internal dispatch pool. This structured planning phase ensures that daily operations remain organized and that resource allocation is optimized well before the first bus leaves the terminal.





     Figure 10: Admin Maintenance Record
Figure 10 illustrates the admin maintenance record procedure, serving as the primary quality control mechanism for fleet longevity and passenger safety. The process begins with the system retrieving a comprehensive log of both historical repairs and pending service alerts. The administrator then classifies the entry as either Preventive Maintenance, triggered by automated mileage or date thresholds, or Corrective Maintenance, initiated by driver-submitted fault reports. Once a work order is created, the system executes a critical status lock, marking the vehicle as "Under Maintenance" to automatically remove it from the available dispatch pool. After the repair details—including parts replaced and labor costs—are documented, a final safety validation check is performed. Only upon administrative confirmation of the bus's operational integrity does the system update the maintenance history and restore the vehicle to "Active" status, ensuring that no compromised units return to service prematurely. 

     Figure 11: Admin Analytics and reports 
Figure 11 illustrates the admin reports and analytics procedure, which transforms raw operational data into actionable strategic insights. The process begins with the system aggregating logs from multiple modules, including trip history, fuel consumption, maintenance expenses, and driver performance metrics. The administrator then selects a specific analytical category and applies temporal filters, such as daily, weekly, or monthly ranges, to narrow the data scope. The system processes this information to generate dynamic visualizations, including performance charts and expense heatmaps, allowing the admin to evaluate key metrics like on-time performance (OTP) and cost-per-kilometer. If a formal record is required, the flow includes a sub-process for exporting these findings into PDF or CSV formats for stakeholder review. This structured evaluation loop enables the administration to identify operational bottlenecks and optimize the overall efficiency of the city's transit service. 

     Figure 12: Admin Service and alerts 
Figure 7 illustrates the admin service alert procedure, which functions as the primary communication bridge between fleet operations and the public during disruptions. The process begins with the administrator identifying a service incident, such as a road closure, weather emergency, or vehicle breakdown. Upon creating a new alert, the admin classifies the severity level—ranging from low-priority information to high-priority route suspensions. The system then requires the definition of a specific scope, allowing the alert to target the entire network or a single route. A critical logical step in this flow is the Route Suspension Toggle; for high-severity incidents, the admin can deactivate a route, which pushes a "Stop" command to the Dispatch module to prevent new trips from entering the affected area. Once published, the system broadcasts the alert simultaneously to the driver terminals for immediate rerouting and to the passenger-facing app for public awareness. The procedure concludes only when the admin manually resolves or archives the alert, ensuring the live map remains accurate and free of outdated warnings.

     Figure 13: Commuter Live Map / Bus tracker
Figure 13  illustrates the Live Map and Bus Tracker procedure for the Commuter in GoPasig. The process begins when the commuter opens the GoPasig web application, which retrieves active bus data from the database and renders all current bus positions and assigned routes on an interactive map. If the commuter selects a specific bus, the system displays its current location, travel speed, estimated arrival time at the next stop, and present passenger capacity. The commuter may optionally set a bus arrival alert or share the bus position link. Regardless of whether a specific bus is selected, the map continues polling for updated coordinates every five to ten seconds. This refresh cycle persists until the commuter chooses to stop tracking, at which point the session ends. 






     Figure 14: Commuter Routes
Figure 14 illustrates the Routes procedure for the Commuter in GoPasig. The process begins when the commuter navigates to the Routes page, which retrieves and displays the list of available Libreng Sakay routes from the database. If the commuter selects a route, the system presents the route detail view showing the complete stop timeline, map overlay, and total estimated travel duration. The page additionally lists all buses currently active on the selected route along with their respective ETAs. The commuter may opt to set an arrival alert for the selected route before exiting. If no route is selected, the session proceeds directly to the end. The commuter may return to browse additional routes as needed. 








     Figure 15: Commuter Stop
Figure 15 illustrates the Stops procedure for the Commuter in GoPasig. The process begins when the commuter accesses the Stops page, which retrieves stop records from the database and displays them ordered by proximity to the commuter's detected location. If the commuter searches by stop name or landmark, the system filters the results accordingly before presenting the matched entries. Upon selecting a stop, the system displays a stop card containing the servicing routes, distance from the commuter, next arriving bus, and estimated arrival time. The stop is additionally pinned on the interactive map for spatial reference. The commuter may repeat this process to check additional stops, and the session concludes once the commuter exits the page.








     Figure 16: Commuter Schedule
Figure 16 illustrates the Schedule procedure for the Commuter in GoPasig. The process begins when the commuter opens the Schedule page, which retrieves schedule records from the database and presents all trip departure and arrival times across available routes. The commuter may filter the schedule by selecting a specific route, which narrows the displayed entries to that route's timetable. For each trip entry, the system indicates whether the service is running on time or experiencing a delay, based on current operational data. The commuter may browse the schedules of additional routes by repeating the filter step, and the session ends once the commuter navigates away from the page.








     Figure 17: Commuter Service alert
Figure 17 illustrates the Service Alerts procedure for the Commuter in GoPasig. The process begins when the commuter opens the Alerts page, which retrieves active alert records from the database and displays them in chronological order. If the commuter applies a category filter — such as delays, route changes, or maintenance — the system narrows the displayed alerts accordingly. Upon selecting an alert, the commuter views its full details including the message content and affected routes. The system branches the display based on alert type: delay alerts are highlighted in amber, service suspension alerts in red, and informational or maintenance alerts in the default style. In all cases, the commuter is shown the affected routes to support trip replanning. The session concludes once the commuter exits the alerts page. 


Figure 18: Fleet Operator Dashboard
	Figure 18 illustrates the System Login procedure for the Fleet Operator and LGU in GoPasig. The process begins when the operator inputs their Operator ID and password, which the system validates against the operators database. If the credentials are invalid, the system redirects to connector A and prompts re-entry. Upon successful authentication, the dashboard is displayed, providing access to all available modules: Overview, Fleet Monitor, Fleet Utilization, Driver Performance, Route Performance, Schedule Compliance, Incidents, Maintenance, and Analytics and Reports. After completing their tasks across any of these modules, the operator proceeds to logout. If the logout is confirmed, the session ends; otherwise, the operator is returned to the dashboard via connector A to continue system use. 


Figure 19: Analytics Module
	Figure 19 illustrates the Analytics and Reports Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving trip records from the database and displaying the analytics dashboard. If the operator elects to generate a report, the system prompts input of the report type, date range, and route filter, after which the system generates and downloads the report document. If no report generation is requested, the process exits via connector J directly to the end. 

Figure 20: Announcement Module
Figure 20 illustrates the Announcements Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving records from the announcements database and printing the current announcement list. If the operator elects to add a new announcement, the system prompts input of the headline, message content, priority level, and target audience. The operator must confirm the entry before it is posted; if confirmation is not provided, the input form is re-presented for correction. Upon confirmation, the system posts the announcement and notifies the designated recipients. If no new announcement is added, the process exits via connector B directly to the end. 

Figure 21: Driver Performance Module
	Figure 21 illustrates the Driver Performance Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving operator records from the database and printing the driver performance list. If the operator selects a specific driver, the system displays detailed analytics, including trip count, performance score, and incident history. The operator may then choose to view the top driver rankings, upon which the system prints the ranked list. If no driver is selected or the rankings are not requested, the process proceeds to the end via connector E. 

Figure 22: Fleet Monitor Module
Figure 22 illustrates the Fleet Monitor Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving live GPS data from the fleet database and rendering all active bus positions on a real-time fleet map. If the operator selects a specific vehicle, the system displays its detailed tracking information including current speed, assigned route, and operational status. The operator may then choose to send a support message to the driver via the dispatch function. If no vehicle is selected, or after the dispatch action is completed, the process proceeds to the end. 


Figure 23: Fleet Utilization Module
Figure 23 illustrates the Fleet Utilization Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving fleet trip records from the database and displaying an overview of utilization statistics across the fleet. If the operator chooses to view bus deployment efficiency, the system presents a visual chart reflecting per-bus efficiency metrics. The operator may then apply filters by route or date range, upon which the system dispatches and displays the filtered results. If no efficiency view or filter is requested, the process proceeds directly to the end.




Figure 24: Incident Module
Figure 24 illustrates the Incidents Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving all incident records from the database and printing the full incident log. If the operator elects to investigate a specific incident, the system displays the full investigation detail for the selected entry. The operator may then mark the incident as resolved, upon which the system updates the incident status to resolved in the database. If the operator does not mark it as resolved, or if no incident is selected for investigation, the process proceeds to the end via connector H. 


Figure 25: Maintenance Module
Figure 25 illustrates the Maintenance Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving fleet health records from the database and displaying the fleet health matrix. If the operator schedules a maintenance entry, the system prompts input of the vehicle, date, maintenance type, technician, and notes. The operator must confirm the entry before it is inserted into the maintenance schedule database. If confirmation is not provided, the input form is re-presented for correction. If no maintenance scheduling is required, the process exits via connector I directly to the end. 



Figure 26: Route Performance Module
Figure 26 illustrates the Route Performance Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving route records from the database and printing the route performance list. If the operator selects a specific route, the system displays stop adherence metrics and headway regularity indicators for that route, followed by a view of recent recorded deviations. If no route is selected, the process exits via connector F directly to the end. 


Figure 27: Schedule Compliance Module
Figure 27 illustrates the Schedule Compliance Module procedure for the Fleet Operator and LGU in GoPasig. The process begins when the module is accessed, retrieving schedule records from the database and displaying a compliance summary across all active routes. If the operator opts to view trip analysis, the system presents a trip-by-trip compliance breakdown detailing adherence for each individual service run. If no trip analysis is requested, the process exits via connector G directly to the end. 


Figure 28: Login and Session Start
Figure 28 illustrates the Login and Session Start procedure for the Bus Driver in GoPasig. The process begins when the driver opens the GoPasig driver application and inputs their Driver ID and password, which the system validates against the driver database. If authentication fails, an error is displayed and the driver is prompted to re-enter their credentials. Upon successful authentication, the dashboard presents the driver's shift details, assigned bus unit, route, and first trip schedule. If the assignment contains discrepancies, the driver contacts dispatch for correction before proceeding. The driver then verifies that the device GPS is active, reviews the shift timeline including completed and upcoming trips, and confirms readiness to begin the shift. 


Figure 29: Live GPS Transmission Update
Figure 29 illustrates the Live GPS Transmission Update procedure for the Bus Driver in GoPasig. The process begins when the driver opens the Trip module, upon which the system verifies that device location permission has been granted. Once confirmed, a GPS session is activated and the device begins capturing geographic coordinates, which are transmitted to the server via HTTP POST every five to ten seconds and logged in the GPS database. If signal is lost, the server retains the last known position and displays a staleness indicator on the commuter interface. For valid transmissions, the server applies Kalman filtering to suppress positional noise before updating the live map and ETA values. This cycle repeats continuously until the driver ends the trip. 


Figure 30: Trip Status
Figure 30 illustrates the Trip Status procedure for the Bus Driver in GoPasig. The process begins when the driver taps the Start Trip button from the home dashboard, which loads the active trip record displaying the trip number, assigned route, and elapsed time. Throughout the trip, the driver updates the passenger count using the increment and decrement controls, and the system displays a capacity warning if the bus reaches full load. The driver monitors their current position and stop progress through the Route module, which reflects passed, current, and upcoming stops in real time. Trip milestones are updated as each stop is completed. The update cycle continues until all stops along the assigned route have been served, at which point the driver marks the route complete and submits the trip log. 



Figure 31: Incident Report
Figure 31 illustrates the Report Incidents procedure for the Bus Driver in GoPasig. The process is initiated from the Trip module during an active trip when the driver encounters a service disruption. The driver selects the applicable report type — incident, delay, or route change — and inputs the relevant details such as a description and current location, which are stored in the incident database. The system prompts the driver to confirm submission before the report is forwarded to dispatch. If the driver cancels, the report is discarded and the trip continues normally. Upon successful submission, the driver may optionally open the Alerts module to send a direct reply or quick acknowledgment to dispatch. The process concludes when the driver awaits a dispatch response and resumes normal trip operations. 






Figure 32: System Flowchart of GoPasig (Part 1 of 3: Login and Role Routing)

Figure 32 illustrates the first part of the GoPasig system flowchart, covering user authentication and role-based routing upon accessing the system. The process begins when a user enters their login credentials, which the system validates; invalid credentials trigger an error message and a retry prompt, while valid credentials proceed to a role check. Based on the assigned role, the system routes the user to one of three interfaces: the Commuter Dashboard for viewing live bus locations, routes, ETA, and service notifications; the Admin Dashboard for fleet operations, which continues in Part 2; or the Driver Interface for trip management, which continues in Part 3.




























Figure 32: System Flowchart of GoPasig (Part 2 of 3: Admin Operations)

Figure 32 illustrates the second part of the GoPasig system flowchart, detailing the administrative operations accessible from the Admin Dashboard established in Part 1. Administrators are presented with a decision point to select between two main operational tracks: Fleet Management and Maintenance. Under Fleet Management, administrators can monitor bus status, manage dispatch assignments by pairing a bus and driver to a route, and upon dispatch confirmation, a trip log is created and stored in the MySQL database. Under Maintenance, administrators can manage repair and inspection records, schedule upcoming maintenance, and flag buses as unavailable when service is required. The workflow from this part continues in Part 3, which covers GPS tracking and report generation.


Figure 32: System Flowchart of GoPasig (Part 3 of 3: GPS Tracking and Reports)
 
Figure 32 illustrates the third and final part of the GoPasig system flowchart, covering real-time GPS tracking, ETA computation, report generation, and session termination, continuing from the trip log created in Part 2. The driver activates the GPS session on their mobile device, transmitting coordinates via HTTP POST every five to ten seconds; if the signal is invalid, the last valid position is retained and a staleness indicator is shown to commuters. Valid coordinates are processed through a Kalman filter to remove noise, after which the Haversine formula calculates the remaining distance to each stop and ETA is computed by dividing that distance by the smoothed speed, with results broadcast to both the commuter map and admin dashboard via Livewire polling. Once the trip is completed, the driver ends the GPS session, the trip log is saved with passenger count, route, and timestamps, and operational reports are generated to support transportation 



System Design and Development Model/Approach 



Figure 33: Agile Model

Source: https://miro.medium.com/0*2BEeiYrLYNdrNoby.png

Figure 33 illustrates the Agile Model adopted as the system design and development approach for GoPasig. The model follows an iterative and incremental process wherein the system is developed through repeated cycles called sprints, each producing a functional increment of the system that is reviewed and refined before the next cycle begins. The process begins with the initial planning phase, where the research team defines the overall system requirements, identifies the core modules to be developed, and establishes the sprint schedule. From this point, the development proceeds through the following recurring phases within each sprint: planning, design, development, testing, and review.
The Agile Model consists of six phases, as follows:
1.The first phase is Requirements Gathering, wherein the research team identified and documented the operational needs of the Pasig City Libreng Sakay Program. This phase established the functional scope of GoPasig, defining the features required for each user role — commuter, driver, administrator, and Fleet Operator — as well as the technical specifications for GPS tracking, ETA computation, and fleet management operations. The gathered requirements served as the basis for the product backlog from which sprint tasks were drawn throughout the development lifecycle.
2.The second phase is System Design, wherein the architectural structure, database schema, interface layouts, and module interactions of GoPasig were defined. This phase produced the system's technical blueprint, including the Laravel 11 MVC framework structure, MySQL relational database design, Livewire component architecture, and Google Maps JavaScript API integration plan. Interface wireframes and prototype screens for the commuter, driver, and administrative interfaces were likewise developed during this phase to guide subsequent implementation.
3.The third phase is Development, wherein the actual coding and integration of each system module were carried out in successive sprint cycles. Each sprint targeted a specific set of features drawn from the product backlog, including the commuter live map interface, driver GPS transmission layer, fleet management dashboard, dispatch control module, maintenance records system, and Fleet Operator analytics modules. Development was performed using Visual Studio Code as the primary IDE, with Laravel 11, Livewire, Tailwind CSS, and the Google Maps JavaScript API as the core technology stack.
4.The fourth phase is Testing, wherein each completed sprint increment was subjected to functional, usability, and performance checks prior to integration with the broader system. Testing activities were aligned with the ISO/IEC 25010 quality characteristics designated as the formal evaluation standard of the study, covering functional suitability, reliability, performance efficiency, usability, security, compatibility, maintainability, and portability. Defects and performance issues identified during testing — including those related to GPS signal attenuation, mobile data intermittency, and API quota constraints — were resolved within the same sprint cycle before proceeding to the review phase.
5.The fifth phase is Review and Feedback, wherein the sprint output was assessed against the defined requirements of the Libreng Sakay Program by the research team. Feedback gathered during each review informed the planning of the succeeding sprint, allowing feature refinements, interface adjustments, and technical corrections to be incorporated iteratively rather than deferred to a final revision stage. This continuous feedback loop ensured that each system module remained aligned with the operational realities of the program throughout the development process.
6.The sixth and final phase is Deployment and Evaluation, wherein the completed and integrated system was prepared for formal evaluation using the ISO/IEC 25010 instrument administered to the intended users and technical evaluators. This phase assessed the acceptability, effectiveness, and suitability of GoPasig in addressing the identified operational gaps of the Pasig City Libreng Sakay Program, with evaluation results serving as the basis for determining the system's overall quality and readiness for institutional adoption.